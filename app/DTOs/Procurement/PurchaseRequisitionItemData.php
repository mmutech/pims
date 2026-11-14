<?php

namespace App\DTOs\Procurement;

use App\Support\Input;
use Illuminate\Support\Arr;

final readonly class PurchaseRequisitionItemData
{
    public function __construct(
        public int $itemId,
        public int $unitOfMeasureId,
        public string $requestedQuantity,
        public string $estimatedUnitPrice = '0',
        public ?string $requiredBy = null,
        public ?string $specification = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            itemId: (int) Arr::get($data, 'item_id'),
            unitOfMeasureId: (int) Arr::get($data, 'unit_of_measure_id'),
            requestedQuantity: (string) Arr::get($data, 'requested_quantity', '0'),
            estimatedUnitPrice: (string) Arr::get($data, 'estimated_unit_price', '0'),
            requiredBy: Input::stringOrNull(Arr::get($data, 'required_by')),
            specification: Input::stringOrNull(Arr::get($data, 'specification')),
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
            'requested_quantity' => $this->requestedQuantity,
            'estimated_unit_price' => $this->estimatedUnitPrice,
            'required_by' => $this->requiredBy,
            'specification' => $this->specification,
        ];
    }
}
