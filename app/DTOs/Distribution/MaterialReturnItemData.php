<?php

namespace App\DTOs\Distribution;

use App\Enums\MaterialCondition;
use App\Support\Input;
use Illuminate\Support\Arr;

final readonly class MaterialReturnItemData
{
    public function __construct(
        public int $itemId,
        public int $unitOfMeasureId,
        public string $returnedQuantity,
        public MaterialCondition $condition = MaterialCondition::Good,
        public string $unitCost = '0',
        public ?int $batchId = null,
        public ?int $serialNumberId = null,
        public ?string $notes = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            itemId: (int) Arr::get($data, 'item_id'),
            unitOfMeasureId: (int) Arr::get($data, 'unit_of_measure_id'),
            returnedQuantity: (string) Arr::get($data, 'returned_quantity', '0'),
            condition: MaterialCondition::from(
                (string) Arr::get($data, 'condition', MaterialCondition::Good->value),
            ),
            unitCost: (string) Arr::get($data, 'unit_cost', '0'),
            batchId: Input::intOrNull(Arr::get($data, 'batch_id')),
            serialNumberId: Input::intOrNull(Arr::get($data, 'serial_number_id')),
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
            'returned_quantity' => $this->returnedQuantity,
            'condition' => $this->condition,
            'unit_cost' => $this->unitCost,
            'batch_id' => $this->batchId,
            'serial_number_id' => $this->serialNumberId,
            'notes' => $this->notes,
        ];
    }
}
