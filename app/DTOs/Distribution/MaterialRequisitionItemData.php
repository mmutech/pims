<?php

namespace App\DTOs\Distribution;

use App\Support\Input;
use Illuminate\Support\Arr;

final readonly class MaterialRequisitionItemData
{
    public function __construct(
        public int $itemId,
        public int $unitOfMeasureId,
        public string $requestedQuantity,
        public ?string $notes = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            itemId: (int) Arr::get($data, 'item_id'),
            unitOfMeasureId: (int) Arr::get($data, 'unit_of_measure_id'),
            requestedQuantity: (string) Arr::get($data, 'requested_quantity', '0'),
            notes: Input::stringOrNull(Arr::get($data, 'notes')),
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
            'notes' => $this->notes,
        ];
    }
}
