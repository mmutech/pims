<?php

namespace App\DTOs\Procurement;

use App\Support\Input;
use Illuminate\Support\Arr;

final readonly class PurchaseOrderData
{
    /**
     * @param  list<PurchaseOrderItemData>  $items
     */
    public function __construct(
        public int $supplierId,
        public int $deliveryWarehouseId,
        public int $createdBy,
        public ?int $purchaseRequisitionId = null,
        public ?int $supplierQuotationId = null,
        public string $currency = 'NGN',
        public ?string $paymentTerms = null,
        public ?string $incoterms = null,
        public ?string $expectedDeliveryDate = null,
        public array $items = [],
    ) {}

    public static function fromArray(array $data): self
    {
        $items = array_map(
            fn ($item) => $item instanceof PurchaseOrderItemData ? $item : PurchaseOrderItemData::fromArray($item),
            (array) Arr::get($data, 'items', []),
        );

        return new self(
            supplierId: (int) Arr::get($data, 'supplier_id'),
            deliveryWarehouseId: (int) Arr::get($data, 'delivery_warehouse_id'),
            createdBy: (int) Arr::get($data, 'created_by'),
            purchaseRequisitionId: Input::intOrNull(Arr::get($data, 'purchase_requisition_id')),
            supplierQuotationId: Input::intOrNull(Arr::get($data, 'supplier_quotation_id')),
            currency: strtoupper((string) Arr::get($data, 'currency', 'NGN')),
            paymentTerms: Input::stringOrNull(Arr::get($data, 'payment_terms')),
            incoterms: Input::stringOrNull(Arr::get($data, 'incoterms')),
            expectedDeliveryDate: Input::stringOrNull(Arr::get($data, 'expected_delivery_date')),
            items: $items,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'supplier_id' => $this->supplierId,
            'delivery_warehouse_id' => $this->deliveryWarehouseId,
            'created_by' => $this->createdBy,
            'purchase_requisition_id' => $this->purchaseRequisitionId,
            'supplier_quotation_id' => $this->supplierQuotationId,
            'currency' => $this->currency,
            'payment_terms' => $this->paymentTerms,
            'incoterms' => $this->incoterms,
            'expected_delivery_date' => $this->expectedDeliveryDate,
        ];
    }
}
