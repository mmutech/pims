<?php

namespace App\Domain\Procurement\Services;

use App\Domain\Administration\Services\DocumentSequenceService;
use App\Domain\Procurement\Models\PurchaseRequisition;
use App\Domain\Procurement\Models\RequestForQuotation;
use App\Domain\Procurement\Models\RfqItem;
use App\Domain\Procurement\Models\RfqSupplier;
use App\Domain\Supplier\Models\Supplier;
use App\DTOs\Procurement\RfqData;
use App\Enums\DocumentType;
use App\Enums\RequestForQuotationStatus;
use App\Enums\RfqSupplierStatus;
use App\Models\User;
use App\Support\Decimal;
use DomainException;
use Illuminate\Support\Facades\DB;

class RequestForQuotationService
{
    public function __construct(
        private readonly DocumentSequenceService $sequences,
    ) {}

    public function create(RfqData $data, User $actor): RequestForQuotation
    {
        $this->validate($data);

        return DB::transaction(function () use ($data, $actor): RequestForQuotation {
            $rfq = RequestForQuotation::create([
                ...$data->toArray(),
                'document_number' => $this->sequences->nextNumber(DocumentType::RequestForQuotation),
                'status' => RequestForQuotationStatus::Draft,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            $this->syncItems($rfq, $data->items);
            $this->syncSuppliers($rfq, $data->supplierIds);

            return $rfq->refresh();
        });
    }

    /**
     * Create an RFQ from the unconverted balance of an approved requisition.
     */
    public function createFromRequisition(PurchaseRequisition $requisition, RfqData $data, User $actor): RequestForQuotation
    {
        if (! $requisition->isApprovedForSourcing()) {
            throw new DomainException('Only approved requisitions can be sourced.');
        }

        $items = [];

        foreach ($requisition->items()->get() as $item) {
            $remaining = $item->remainingQuantity();

            if (Decimal::compare($remaining, '0') <= 0) {
                continue;
            }

            $requested = $data->items !== []
                ? collect($data->items)->firstWhere('purchase_requisition_item_id', $item->id)
                : null;

            $quantity = $requested !== null
                ? Decimal::normalize((string) ($requested['quantity'] ?? $remaining), Decimal::QUANTITY_SCALE)
                : $remaining;

            if (Decimal::compare($quantity, $remaining) > 0) {
                throw new DomainException('RFQ quantity cannot exceed the approved requisition balance.');
            }

            $items[] = [
                'purchase_requisition_item_id' => $item->id,
                'item_id' => $item->item_id,
                'unit_of_measure_id' => $item->unit_of_measure_id,
                'quantity' => $quantity,
                'specification' => $item->specification,
            ];
        }

        if ($items === []) {
            throw new DomainException('The requisition has no remaining quantity to source.');
        }

        return DB::transaction(function () use ($requisition, $data, $actor, $items): RequestForQuotation {
            $rfq = RequestForQuotation::create([
                ...$data->toArray(),
                'purchase_requisition_id' => $requisition->id,
                'document_number' => $this->sequences->nextNumber(DocumentType::RequestForQuotation),
                'status' => RequestForQuotationStatus::Draft,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            $this->syncItems($rfq, $items);
            $this->syncSuppliers($rfq, $data->supplierIds);

            return $rfq->refresh();
        });
    }

    public function update(RequestForQuotation $rfq, RfqData $data, User $actor): RequestForQuotation
    {
        if (! $rfq->canBeEdited()) {
            throw new DomainException('Only draft RFQs can be edited.');
        }

        $this->validate($data);

        return DB::transaction(function () use ($rfq, $data, $actor): RequestForQuotation {
            $this->releaseConversions($rfq);

            $rfq->update([
                ...$data->toArray(),
                'updated_by' => $actor->id,
            ]);

            $rfq->items()->delete();
            $rfq->suppliers()->delete();

            $this->syncItems($rfq, $data->items);
            $this->syncSuppliers($rfq, $data->supplierIds);

            return $rfq->refresh();
        });
    }

    public function issue(RequestForQuotation $rfq, User $actor): RequestForQuotation
    {
        if (! $rfq->canBeIssued()) {
            throw new DomainException('This RFQ cannot be issued.');
        }

        if (! $rfq->items()->exists()) {
            throw new DomainException('An RFQ must contain at least one item before it is issued.');
        }

        if (! $rfq->suppliers()->exists()) {
            throw new DomainException('Invite at least one supplier before issuing the RFQ.');
        }

        if ($rfq->closing_date->isPast()) {
            throw new DomainException('The closing date must be in the future.');
        }

        $rfq->forceFill([
            'status' => RequestForQuotationStatus::Issued,
            'issued_at' => now(),
            'updated_by' => $actor->id,
        ])->save();

        return $rfq->refresh();
    }

    public function close(RequestForQuotation $rfq, User $actor): RequestForQuotation
    {
        if ($rfq->status !== RequestForQuotationStatus::Issued) {
            throw new DomainException('Only issued RFQs can be closed.');
        }

        $rfq->forceFill([
            'status' => RequestForQuotationStatus::Closed,
            'closed_at' => now(),
            'updated_by' => $actor->id,
        ])->save();

        return $rfq->refresh();
    }

    public function cancel(RequestForQuotation $rfq, User $actor): RequestForQuotation
    {
        if (! $rfq->canBeCancelled()) {
            throw new DomainException('This RFQ cannot be cancelled.');
        }

        return DB::transaction(function () use ($rfq, $actor): RequestForQuotation {
            $this->releaseConversions($rfq);

            $rfq->forceFill([
                'status' => RequestForQuotationStatus::Cancelled,
                'cancelled_at' => now(),
                'updated_by' => $actor->id,
            ])->save();

            return $rfq->refresh();
        });
    }

    public function addSupplier(RequestForQuotation $rfq, Supplier $supplier): RfqSupplier
    {
        if (! $rfq->canBeEdited()) {
            throw new DomainException('Suppliers can only be invited while the RFQ is a draft.');
        }

        return RfqSupplier::firstOrCreate(
            ['request_for_quotation_id' => $rfq->id, 'supplier_id' => $supplier->id],
            ['invited_at' => now(), 'status' => RfqSupplierStatus::Invited],
        );
    }

    public function removeSupplier(RequestForQuotation $rfq, RfqSupplier $rfqSupplier): void
    {
        if (! $rfq->canBeEdited()) {
            throw new DomainException('Suppliers can only be removed while the RFQ is a draft.');
        }

        $rfqSupplier->delete();
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function syncItems(RequestForQuotation $rfq, array $items): void
    {
        foreach (array_values($items) as $index => $item) {
            $quantity = Decimal::normalize((string) ($item['quantity'] ?? '0'), Decimal::QUANTITY_SCALE);

            if (Decimal::compare($quantity, '0') <= 0) {
                throw new DomainException('RFQ quantities must be greater than zero.');
            }

            $prItemId = $item['purchase_requisition_item_id'] ?? null;

            if ($prItemId !== null) {
                $prItem = $rfq->purchaseRequisition?->items()->find($prItemId);

                if ($prItem === null) {
                    throw new DomainException('The linked requisition line does not belong to this requisition.');
                }

                $remaining = $prItem->remainingQuantity();

                if (Decimal::compare($quantity, $remaining) > 0) {
                    throw new DomainException('RFQ quantity cannot exceed the approved requisition balance.');
                }

                $prItem->forceFill([
                    'converted_quantity' => Decimal::add($prItem->converted_quantity, $quantity, Decimal::QUANTITY_SCALE),
                ])->save();
            }

            RfqItem::create([
                'request_for_quotation_id' => $rfq->id,
                'purchase_requisition_item_id' => $prItemId,
                'item_id' => (int) $item['item_id'],
                'unit_of_measure_id' => (int) $item['unit_of_measure_id'],
                'quantity' => $quantity,
                'specification' => $item['specification'] ?? null,
                'sort_order' => $index,
            ]);
        }
    }

    /**
     * @param  list<int>  $supplierIds
     */
    private function syncSuppliers(RequestForQuotation $rfq, array $supplierIds): void
    {
        foreach (array_unique($supplierIds) as $supplierId) {
            RfqSupplier::create([
                'request_for_quotation_id' => $rfq->id,
                'supplier_id' => $supplierId,
                'invited_at' => now(),
                'status' => RfqSupplierStatus::Invited,
            ]);
        }
    }

    private function releaseConversions(RequestForQuotation $rfq): void
    {
        foreach ($rfq->items()->whereNotNull('purchase_requisition_item_id')->get() as $rfqItem) {
            $prItem = $rfqItem->purchaseRequisitionItem;

            if ($prItem === null) {
                continue;
            }

            $prItem->forceFill([
                'converted_quantity' => Decimal::subtract(
                    $prItem->converted_quantity,
                    $rfqItem->quantity,
                    Decimal::QUANTITY_SCALE,
                ),
            ])->save();
        }
    }

    private function validate(RfqData $data): void
    {
        if ($data->title === '') {
            throw new DomainException('An RFQ title is required.');
        }

        if ($data->items === []) {
            throw new DomainException('An RFQ must contain at least one item.');
        }
    }
}
