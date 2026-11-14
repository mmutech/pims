<?php

namespace App\Domain\Procurement\Services;

use App\Domain\Procurement\Models\PurchaseOrder;
use App\Domain\Procurement\Models\PurchaseOrderAmendment;
use App\Domain\Procurement\Models\PurchaseOrderAmendmentItem;
use App\Domain\Workflow\Contracts\ApprovalEngine;
use App\Domain\Workflow\Models\ApprovalRequest;
use App\Enums\PurchaseOrderAmendmentStatus;
use App\Models\User;
use App\Support\Decimal;
use DomainException;
use Illuminate\Support\Facades\DB;

class PurchaseOrderAmendmentService
{
    public function __construct(
        private readonly ApprovalEngine $approvals,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $items
     */
    public function create(PurchaseOrder $order, string $reason, array $items, User $actor): PurchaseOrderAmendment
    {
        if (! $order->canBeAmended()) {
            throw new DomainException('Only approved purchase orders can be amended.');
        }

        if (blank($reason)) {
            throw new DomainException('An amendment reason is required.');
        }

        return DB::transaction(function () use ($order, $reason, $items, $actor): PurchaseOrderAmendment {
            $number = (int) $order->amendments()->max('amendment_number') + 1;

            $amendment = PurchaseOrderAmendment::create([
                'purchase_order_id' => $order->id,
                'amendment_number' => $number,
                'reason' => $reason,
                'status' => PurchaseOrderAmendmentStatus::Draft,
                'requested_by' => $actor->id,
            ]);

            $this->syncItems($amendment, $order, $items);

            return $amendment->refresh();
        });
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    public function update(PurchaseOrderAmendment $amendment, string $reason, array $items): PurchaseOrderAmendment
    {
        if (! $amendment->canBeEdited()) {
            throw new DomainException('Only draft or returned amendments can be edited.');
        }

        if (blank($reason)) {
            throw new DomainException('An amendment reason is required.');
        }

        return DB::transaction(function () use ($amendment, $reason, $items): PurchaseOrderAmendment {
            $amendment->update(['reason' => $reason]);
            $amendment->items()->delete();
            $this->syncItems($amendment, $amendment->purchaseOrder, $items);

            return $amendment->refresh();
        });
    }

    public function submit(PurchaseOrderAmendment $amendment, User $actor): PurchaseOrderAmendment
    {
        if (! $amendment->canBeSubmitted()) {
            throw new DomainException('This amendment cannot be submitted.');
        }

        return DB::transaction(function () use ($amendment, $actor): PurchaseOrderAmendment {
            $amendment->forceFill([
                'status' => PurchaseOrderAmendmentStatus::PendingApproval,
                'submitted_at' => now(),
            ])->save();

            $this->approvals->submit($amendment, $actor);

            return $amendment->refresh();
        });
    }

    public function cancel(PurchaseOrderAmendment $amendment, User $actor, string $reason): PurchaseOrderAmendment
    {
        if (in_array($amendment->status, [
            PurchaseOrderAmendmentStatus::Approved,
            PurchaseOrderAmendmentStatus::Cancelled,
        ], true)) {
            throw new DomainException('This amendment cannot be cancelled.');
        }

        return DB::transaction(function () use ($amendment, $actor, $reason): PurchaseOrderAmendment {
            $request = ApprovalRequest::query()
                ->where('document_type', $amendment->approvalDocumentType())
                ->where('document_id', $amendment->id)
                ->where('status', 'pending')
                ->first();

            if ($request !== null) {
                $this->approvals->cancel($request, $actor, $reason);
            }

            $amendment->forceFill(['status' => PurchaseOrderAmendmentStatus::Cancelled])->save();

            return $amendment->refresh();
        });
    }

    /**
     * Apply an approved amendment to the purchase order and bump its version.
     */
    public function apply(PurchaseOrderAmendment $amendment): PurchaseOrder
    {
        return DB::transaction(function () use ($amendment): PurchaseOrder {
            $order = $amendment->purchaseOrder()->lockForUpdate()->firstOrFail();

            foreach ($amendment->items()->get() as $row) {
                /** @var PurchaseOrderAmendmentItem $row */
                $item = $order->items()->find($row->purchase_order_item_id);

                if ($item === null) {
                    continue;
                }

                if (Decimal::compare($row->amended_quantity, $item->received_quantity) < 0) {
                    throw new DomainException('Amended quantities cannot fall below the quantity already received.');
                }

                $item->forceFill([
                    'ordered_quantity' => $row->amended_quantity,
                    'unit_price' => $row->amended_unit_price,
                    'line_total' => $row->amended_line_total,
                ])->save();
            }

            $order->recalculateTotals();
            $order->forceFill([
                'version' => $order->version + 1,
                'updated_by' => $amendment->approved_by,
            ])->save();

            return $order->refresh();
        });
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function syncItems(PurchaseOrderAmendment $amendment, PurchaseOrder $order, array $items): void
    {
        if ($items === []) {
            throw new DomainException('An amendment must include at least one line.');
        }

        foreach ($items as $row) {
            $item = $order->items()->find((int) ($row['purchase_order_item_id'] ?? 0));

            if ($item === null) {
                throw new DomainException('Amendment lines must reference items from the purchase order.');
            }

            $quantity = Decimal::normalize(
                (string) ($row['amended_quantity'] ?? $item->ordered_quantity),
                Decimal::QUANTITY_SCALE,
            );
            $unitPrice = Decimal::normalize(
                (string) ($row['amended_unit_price'] ?? $item->unit_price),
                Decimal::QUANTITY_SCALE,
            );

            if (Decimal::compare($quantity, $item->received_quantity) < 0) {
                throw new DomainException('Amended quantities cannot fall below the quantity already received.');
            }

            if (Decimal::compare($unitPrice, '0') < 0) {
                throw new DomainException('Amended unit prices cannot be negative.');
            }

            PurchaseOrderAmendmentItem::create([
                'purchase_order_amendment_id' => $amendment->id,
                'purchase_order_item_id' => $item->id,
                'original_quantity' => $item->ordered_quantity,
                'amended_quantity' => $quantity,
                'original_unit_price' => $item->unit_price,
                'amended_unit_price' => $unitPrice,
                'original_line_total' => $item->line_total,
                'amended_line_total' => Decimal::multiply($quantity, $unitPrice, Decimal::MONEY_SCALE),
            ]);
        }
    }
}
