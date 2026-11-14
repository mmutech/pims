<?php

namespace App\DTOs\Procurement;

use App\Support\Input;
use Illuminate\Support\Arr;

final readonly class PurchaseOrderItemData
{
    public function __construct(
        public int $itemId,
        public int $unitOfMeasureId,
        public string $orderedQuantity,
        public string $unitPrice,
        public string $taxRate = '0',
        public string $discountAmount = '0',
        public ?string $requiredBy = null,
        public ?int $costCenterId = null,
        public ?int $projectId = null,
        public ?int $purchaseRequisitionItemId = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            itemId: (int) Arr::get($data, 'item_id'),
            unitOfMeasureId: (int) Arr::get($data, 'unit_of_measure_id'),
            orderedQuantity: (string) Arr::get($data, 'ordered_quantity', '0'),
            unitPrice: (string) Arr::get($data, 'unit_price', '0'),
            taxRate: (string) Arr::get($data, 'tax_rate', '0'),
            discountAmount: (string) Arr::get($data, 'discount_amount', '0'),
            requiredBy: Input::stringOrNull(Arr::get($data, 'required_by')),
            costCenterId: Input::intOrNull(Arr::get($data, 'cost_center_id')),
            projectId: Input::intOrNull(Arr::get($data, 'project_id')),
            purchaseRequisitionItemId: Input::intOrNull(Arr::get($data, 'purchase_requisition_item_id')),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'item_id' => $this->itemId,
            'unit_of_measure_id' => $this->unitOfMeasureId,
            'ordered_quantity' => $this->orderedQuantity,
            'unit_price' => $this->unitPrice,
            'tax_rate' => $this->taxRate,
            'discount_amount' => $this->discountAmount,
            'required_by' => $this->requiredBy,
            'cost_center_id' => $this->costCenterId,
            'project_id' => $this->projectId,
            'purchase_requisition_item_id' => $this->purchaseRequisitionItemId,
        ];
    }
}
