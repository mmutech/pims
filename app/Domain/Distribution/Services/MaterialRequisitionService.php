<?php

namespace App\Domain\Distribution\Services;

use App\Domain\Administration\Services\DocumentSequenceService;
use App\Domain\Distribution\Models\MaterialBackorder;
use App\Domain\Distribution\Models\MaterialRequisition;
use App\Domain\Distribution\Models\MaterialRequisitionItem;
use App\Domain\Inventory\Models\InventoryBalance;
use App\Domain\Inventory\Models\StockReservation;
use App\Domain\Inventory\Services\StockReservationService;
use App\Domain\Workflow\Contracts\ApprovalEngine;
use App\Domain\Workflow\Models\ApprovalRequest;
use App\DTOs\Distribution\MaterialRequisitionData;
use App\DTOs\Inventory\StockReservationData;
use App\DTOs\Inventory\StockReservationLineData;
use App\Enums\DocumentType;
use App\Enums\MaterialBackorderStatus;
use App\Enums\RequisitionStatus;
use App\Enums\StockDimensionType;
use App\Models\User;
use App\Support\Decimal;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Material requisition lifecycle: request, approval, warehouse reservation and
 * shortfall backorders. Physical movements belong to StockIssueService.
 */
class MaterialRequisitionService
{
    public function __construct(
        private readonly DocumentSequenceService $sequences,
        private readonly ApprovalEngine $approvals,
        private readonly StockReservationService $reservations,
    ) {}

    public function create(MaterialRequisitionData $data, User $actor): MaterialRequisition
    {
        $this->validateItems($data);

        return DB::transaction(function () use ($data, $actor): MaterialRequisition {
            $requisition = MaterialRequisition::create([
                ...$data->toArray(),
                'document_number' => $this->sequences->nextNumber(DocumentType::MaterialRequisition),
                'status' => RequisitionStatus::Draft,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            $this->syncItems($requisition, $data);

            return $requisition->refresh()->load('items');
        });
    }

    public function update(MaterialRequisition $requisition, MaterialRequisitionData $data, User $actor): MaterialRequisition
    {
        if (! $requisition->canBeEdited()) {
            throw new DomainException('Only draft or returned requisitions can be edited.');
        }

        $this->validateItems($data);

        return DB::transaction(function () use ($requisition, $data, $actor): MaterialRequisition {
            $requisition->update([
                ...$data->toArray(),
                'updated_by' => $actor->id,
            ]);

            $requisition->items()->delete();
            $this->syncItems($requisition, $data);

            return $requisition->refresh()->load('items');
        });
    }

    /**
     * Approvers may reduce approved quantities while the request is pending.
     *
     * @param  array<int, string>  $quantities  requisition item id => approved quantity
     */
    public function updateApprovedQuantities(MaterialRequisition $requisition, array $quantities, User $actor): MaterialRequisition
    {
        if (! in_array($requisition->status, [
            RequisitionStatus::Draft,
            RequisitionStatus::Submitted,
            RequisitionStatus::Returned,
        ], true)) {
            throw new DomainException('Approved quantities can only be adjusted before the requisition is approved.');
        }

        return DB::transaction(function () use ($requisition, $quantities, $actor): MaterialRequisition {
            foreach ($requisition->items()->get() as $item) {
                if (! array_key_exists($item->id, $quantities)) {
                    continue;
                }

                $approved = Decimal::normalize((string) $quantities[$item->id], Decimal::QUANTITY_SCALE);

                if (Decimal::compare($approved, '0') < 0) {
                    throw new DomainException('Approved quantities cannot be negative.');
                }

                if (Decimal::compare($approved, $item->requested_quantity) > 0) {
                    throw new DomainException('Approved quantities cannot exceed the requested quantity.');
                }

                if (Decimal::compare($approved, $item->issued_quantity) < 0) {
                    throw new DomainException('Approved quantities cannot be below the already issued quantity.');
                }

                $item->forceFill([
                    'approved_quantity' => $approved,
                    'outstanding_quantity' => Decimal::subtract(
                        $approved,
                        $item->issued_quantity,
                        Decimal::QUANTITY_SCALE,
                    ),
                ])->save();
            }

            $requisition->forceFill(['updated_by' => $actor->id])->save();

            return $requisition->refresh()->load('items');
        });
    }

    public function submit(MaterialRequisition $requisition, User $actor): MaterialRequisition
    {
        if (! $requisition->canBeSubmitted()) {
            throw new DomainException('This requisition cannot be submitted.');
        }

        if (! $requisition->items()->exists()) {
            throw new DomainException('A material requisition must contain at least one item.');
        }

        return DB::transaction(function () use ($requisition, $actor): MaterialRequisition {
            foreach ($requisition->items()->get() as $item) {
                $item->forceFill(['approved_quantity' => $item->requested_quantity])->save();
            }

            $requisition->forceFill([
                'status' => RequisitionStatus::Submitted,
                'updated_by' => $actor->id,
            ])->save();

            $this->approvals->submit($requisition, $actor);

            return $requisition->refresh()->load('items');
        });
    }

    /**
     * Allocate approved quantities across available warehouse stock.
     *
     * Quantities that cannot be covered become open material backorders.
     */
    public function reserve(MaterialRequisition $requisition, User $actor): MaterialRequisition
    {
        return DB::transaction(function () use ($requisition, $actor): MaterialRequisition {
            $requisition = MaterialRequisition::query()->lockForUpdate()->findOrFail($requisition->id);

            if (! $requisition->isApprovedForIssue()) {
                throw new DomainException('Only approved requisitions can reserve stock.');
            }

            if (StockReservation::query()
                ->where('source_type', $requisition->getMorphClass())
                ->where('source_id', $requisition->id)
                ->open()
                ->exists()) {
                throw new DomainException('An open reservation already exists for this requisition.');
            }

            $items = $requisition->items()->get();
            $lines = [];
            $allocations = [];
            $backorders = [];

            foreach ($items as $item) {
                $remaining = Decimal::subtract(
                    $item->outstanding_quantity,
                    $item->reserved_quantity,
                    Decimal::QUANTITY_SCALE,
                );

                if (! Decimal::isPositive($remaining)) {
                    continue;
                }

                $balances = InventoryBalance::query()
                    ->where('warehouse_id', $requisition->destination_warehouse_id)
                    ->where('item_id', $item->item_id)
                    ->where('available_quantity', '>', 0)
                    ->whereHas('dimension', fn ($query) => $query
                        ->where('type', StockDimensionType::Warehouse->value)
                        ->where('is_active', true))
                    ->orderBy('batch_key')
                    ->orderBy('id')
                    ->get();

                foreach ($balances as $balance) {
                    if (! Decimal::isPositive($remaining)) {
                        break;
                    }

                    $available = Decimal::normalize($balance->available_quantity, Decimal::QUANTITY_SCALE);

                    if (! Decimal::isPositive($available)) {
                        continue;
                    }

                    $take = Decimal::compare($remaining, $available) <= 0 ? $remaining : $available;

                    $lines[] = new StockReservationLineData(
                        itemId: $item->item_id,
                        stockDimensionId: $balance->stock_dimension_id,
                        quantity: $take,
                        batchId: $balance->batch_id,
                        reference: 'requisition_item:'.$item->id,
                    );

                    $allocations[] = ['item' => $item, 'quantity' => $take];
                    $remaining = Decimal::subtract($remaining, $take, Decimal::QUANTITY_SCALE);
                }

                if (Decimal::isPositive($remaining)) {
                    $backorders[] = ['item' => $item, 'quantity' => $remaining];
                }
            }

            if ($lines !== []) {
                $this->reservations->reserve(new StockReservationData(
                    source: $requisition,
                    warehouseId: $requisition->destination_warehouse_id,
                    lines: $lines,
                    createdBy: $actor->id,
                ));

                foreach ($allocations as $allocation) {
                    /** @var MaterialRequisitionItem $item */
                    $item = $allocation['item'];
                    $item->forceFill([
                        'reserved_quantity' => Decimal::add(
                            $item->reserved_quantity,
                            $allocation['quantity'],
                            Decimal::QUANTITY_SCALE,
                        ),
                    ])->save();
                }
            }

            foreach ($backorders as $backorder) {
                /** @var MaterialRequisitionItem $item */
                $item = $backorder['item'];

                MaterialBackorder::query()->updateOrCreate(
                    [
                        'material_requisition_item_id' => $item->id,
                        'warehouse_id' => $requisition->destination_warehouse_id,
                        'status' => MaterialBackorderStatus::Open,
                    ],
                    ['outstanding_quantity' => $backorder['quantity']],
                );
            }

            if ($lines !== []) {
                $requisition->forceFill(['status' => RequisitionStatus::Reserved])->save();
            }

            return $requisition->refresh()->load('items');
        });
    }

    public function cancel(MaterialRequisition $requisition, User $actor, string $reason): MaterialRequisition
    {
        if (! $requisition->canBeCancelled()) {
            throw new DomainException('This requisition cannot be cancelled.');
        }

        if (blank($reason)) {
            throw new DomainException('A cancellation reason is required.');
        }

        return DB::transaction(function () use ($requisition, $actor, $reason): MaterialRequisition {
            $this->cancelPendingApproval($requisition, $actor, $reason);
            $this->releaseReservation($requisition, $actor, 'Requisition cancelled: '.$reason);

            MaterialBackorder::query()
                ->whereIn('material_requisition_item_id', $requisition->items()->select('id'))
                ->where('status', MaterialBackorderStatus::Open->value)
                ->update(['status' => MaterialBackorderStatus::Cancelled->value]);

            $requisition->forceFill([
                'status' => RequisitionStatus::Cancelled,
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
                'updated_by' => $actor->id,
            ])->save();

            return $requisition->refresh()->load('items');
        });
    }

    public function delete(MaterialRequisition $requisition): void
    {
        if (! $requisition->canBeEdited()) {
            throw new DomainException('Only draft or returned requisitions can be deleted.');
        }

        $requisition->delete();
    }

    /**
     * Release any open reservation and reset reserved projections.
     */
    public function releaseReservation(MaterialRequisition $requisition, User $actor, string $reason): void
    {
        $reservation = StockReservation::query()
            ->where('source_type', $requisition->getMorphClass())
            ->where('source_id', $requisition->id)
            ->open()
            ->lockForUpdate()
            ->first();

        if ($reservation !== null) {
            $this->reservations->release($reservation, $reason, [], $actor);
        }

        $requisition->items()->update(['reserved_quantity' => '0']);
    }

    private function syncItems(MaterialRequisition $requisition, MaterialRequisitionData $data): void
    {
        foreach ($data->items as $index => $item) {
            MaterialRequisitionItem::create([
                'material_requisition_id' => $requisition->id,
                ...$item->toArray(),
                'approved_quantity' => '0',
                'reserved_quantity' => '0',
                'issued_quantity' => '0',
                'outstanding_quantity' => '0',
                'sort_order' => $index,
            ]);
        }
    }

    private function validateItems(MaterialRequisitionData $data): void
    {
        if ($data->items === []) {
            throw new DomainException('A material requisition must contain at least one item.');
        }

        if (blank(trim($data->purpose))) {
            throw new DomainException('A requisition purpose is required.');
        }

        $seen = [];

        foreach ($data->items as $item) {
            if (Decimal::compare($item->requestedQuantity, '0') <= 0) {
                throw new DomainException('Requested quantities must be greater than zero.');
            }

            if (isset($seen[$item->itemId])) {
                throw new DomainException('Duplicate item lines are not allowed. Combine quantities instead.');
            }

            $seen[$item->itemId] = true;
        }
    }

    private function cancelPendingApproval(MaterialRequisition $requisition, User $actor, string $reason): void
    {
        $request = ApprovalRequest::query()
            ->where('document_type', $requisition->approvalDocumentType())
            ->where('document_id', $requisition->id)
            ->where('status', 'pending')
            ->first();

        if ($request !== null) {
            $this->approvals->cancel($request, $actor, $reason);
        }
    }
}
