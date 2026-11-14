<?php

namespace App\Domain\Procurement\Services;

use App\Domain\Administration\Services\DocumentSequenceService;
use App\Domain\Procurement\Models\PurchaseOrder;
use App\Domain\Procurement\Models\PurchaseOrderItem;
use App\Domain\Procurement\Models\PurchaseRequisition;
use App\Domain\Procurement\Models\SupplierQuotation;
use App\Domain\Workflow\Contracts\ApprovalEngine;
use App\Domain\Workflow\Models\ApprovalRequest;
use App\DTOs\Procurement\PurchaseOrderData;
use App\DTOs\Procurement\PurchaseOrderItemData;
use App\Enums\DocumentType;
use App\Enums\PurchaseOrderStatus;
use App\Enums\SupplierQuotationStatus;
use App\Models\User;
use App\Support\Decimal;
use DomainException;
use Illuminate\Support\Facades\DB;

class PurchaseOrderService
{
    public function __construct(
        private readonly DocumentSequenceService $sequences,
        private readonly ApprovalEngine $approvals,
    ) {}

    public function create(PurchaseOrderData $data, User $actor): PurchaseOrder
    {
        $this->assertSourcing($data);
        $this->validateItems($data);

        return DB::transaction(function () use ($data, $actor): PurchaseOrder {
            $order = PurchaseOrder::create([
                ...$data->toArray(),
                'document_number' => $this->sequences->nextNumber(DocumentType::PurchaseOrder),
                'status' => PurchaseOrderStatus::Draft,
                'subtotal' => '0',
                'tax_amount' => '0',
                'total_amount' => '0',
                'version' => 1,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            $this->syncItems($order, $data);
            $order->recalculateTotals();

            return $order->refresh();
        });
    }

    /**
     * Build an order from an accepted supplier quotation.
     */
    public function createFromQuotation(
        SupplierQuotation $quotation,
        int $deliveryWarehouseId,
        User $actor,
        ?string $paymentTerms = null,
        ?string $expectedDeliveryDate = null,
    ): PurchaseOrder {
        if ($quotation->status !== SupplierQuotationStatus::Accepted) {
            throw new DomainException('Only accepted quotations can be converted into a purchase order.');
        }

        $items = $quotation->items()->get()->map(fn ($item) => new PurchaseOrderItemData(
            itemId: $item->item_id,
            unitOfMeasureId: $item->unit_of_measure_id,
            orderedQuantity: $item->quantity,
            unitPrice: $item->unit_price,
            taxRate: $item->tax_rate,
            discountAmount: $item->discount_amount,
            requiredBy: null,
        ))->all();

        $data = new PurchaseOrderData(
            supplierId: $quotation->supplier_id,
            deliveryWarehouseId: $deliveryWarehouseId,
            createdBy: $actor->id,
            purchaseRequisitionId: $quotation->requestForQuotation->purchase_requisition_id,
            supplierQuotationId: $quotation->id,
            currency: $quotation->currency,
            paymentTerms: $paymentTerms ?? $quotation->payment_terms,
            expectedDeliveryDate: $expectedDeliveryDate,
            items: $items,
        );

        return $this->create($data, $actor);
    }

    public function update(PurchaseOrder $order, PurchaseOrderData $data, User $actor): PurchaseOrder
    {
        if (! $order->canBeEdited()) {
            throw new DomainException('Only draft purchase orders can be edited.');
        }

        $this->assertSourcing($data);
        $this->validateItems($data);

        return DB::transaction(function () use ($order, $data, $actor): PurchaseOrder {
            $order->update([
                ...$data->toArray(),
                'updated_by' => $actor->id,
            ]);

            $order->items()->delete();
            $this->syncItems($order, $data);
            $order->recalculateTotals();

            return $order->refresh();
        });
    }

    public function submit(PurchaseOrder $order, User $actor): PurchaseOrder
    {
        if (! $order->canBeSubmitted()) {
            throw new DomainException('This purchase order cannot be submitted.');
        }

        if (! $order->items()->exists()) {
            throw new DomainException('A purchase order must contain at least one line.');
        }

        return DB::transaction(function () use ($order, $actor): PurchaseOrder {
            $order->forceFill([
                'status' => PurchaseOrderStatus::PendingApproval,
                'updated_by' => $actor->id,
            ])->save();

            $this->approvals->submit($order, $actor);

            return $order->refresh();
        });
    }

    public function cancel(PurchaseOrder $order, User $actor, string $reason): PurchaseOrder
    {
        if (! $order->canBeCancelled()) {
            throw new DomainException('This purchase order cannot be cancelled.');
        }

        if (blank($reason)) {
            throw new DomainException('A cancellation reason is required.');
        }

        return DB::transaction(function () use ($order, $actor, $reason): PurchaseOrder {
            $request = ApprovalRequest::query()
                ->where('document_type', $order->approvalDocumentType())
                ->where('document_id', $order->id)
                ->where('status', 'pending')
                ->first();

            if ($request !== null) {
                $this->approvals->cancel($request, $actor, $reason);
            }

            $order->forceFill([
                'status' => PurchaseOrderStatus::Cancelled,
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
                'updated_by' => $actor->id,
            ])->save();

            return $order->refresh();
        });
    }

    public function close(PurchaseOrder $order, User $actor): PurchaseOrder
    {
        if (! $order->canBeClosed()) {
            throw new DomainException('This purchase order cannot be closed.');
        }

        $order->forceFill([
            'status' => PurchaseOrderStatus::Closed,
            'closed_at' => now(),
            'updated_by' => $actor->id,
        ])->save();

        return $order->refresh();
    }

    public function delete(PurchaseOrder $order): void
    {
        if (! $order->canBeEdited()) {
            throw new DomainException('Only draft purchase orders can be deleted.');
        }

        $order->delete();
    }

    private function assertSourcing(PurchaseOrderData $data): void
    {
        if ($data->supplierQuotationId !== null) {
            $quotation = SupplierQuotation::query()->find($data->supplierQuotationId);

            if ($quotation === null) {
                throw new DomainException('The sourcing quotation could not be found.');
            }

            if ($quotation->status !== SupplierQuotationStatus::Accepted) {
                throw new DomainException('The supplier quotation must be accepted by an approved evaluation.');
            }

            if ($quotation->supplier_id !== $data->supplierId) {
                throw new DomainException('The purchase order supplier must match the accepted quotation.');
            }

            return;
        }

        if ($data->purchaseRequisitionId !== null) {
            $requisition = PurchaseRequisition::query()->find($data->purchaseRequisitionId);

            if ($requisition === null || ! $requisition->isApprovedForSourcing()) {
                throw new DomainException('The purchase order must reference an approved requisition.');
            }

            return;
        }

        if (! config('procurement.allow_unsourced_orders', false)) {
            throw new DomainException('A purchase order requires an accepted quotation or approved requisition.');
        }
    }

    private function syncItems(PurchaseOrder $order, PurchaseOrderData $data): void
    {
        foreach ($data->items as $index => $item) {
            PurchaseOrderItem::create([
                'purchase_order_id' => $order->id,
                ...$item->toArray(),
                'line_total' => Decimal::multiply($item->orderedQuantity, $item->unitPrice, Decimal::MONEY_SCALE),
                'sort_order' => $index,
            ]);
        }
    }

    private function validateItems(PurchaseOrderData $data): void
    {
        if ($data->items === []) {
            throw new DomainException('A purchase order must contain at least one line.');
        }

        foreach ($data->items as $item) {
            if (Decimal::compare($item->orderedQuantity, '0') <= 0) {
                throw new DomainException('Ordered quantities must be greater than zero.');
            }

            if (Decimal::compare($item->unitPrice, '0') < 0) {
                throw new DomainException('Unit prices cannot be negative.');
            }
        }
    }
}
