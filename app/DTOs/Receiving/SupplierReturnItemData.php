<?php

namespace App\DTOs\Receiving;

use App\Support\Input;
use Illuminate\Support\Arr;

final readonly class SupplierReturnItemData
{
    /**
     * @param  list<string>  $serialNumbers
     */
    public function __construct(
        public int $itemId,
        public int $unitOfMeasureId,
        public string $quantity,
        public string $unitCost = '0',
        public ?int $batchId = null,
        public array $serialNumbers = [],
        public string $condition = 'good',
    ) {}

    public static function fromArray(array $data): self
    {
        $serials = Arr::get($data, 'serial_numbers', []);

        return new self(
            itemId: (int) Arr::get($data, 'item_id'),
            unitOfMeasureId: (int) Arr::get($data, 'unit_of_measure_id'),
            quantity: (string) Arr::get($data, 'quantity', '0'),
            unitCost: (string) Arr::get($data, 'unit_cost', '0'),
            batchId: Input::intOrNull(Arr::get($data, 'batch_id')),
            serialNumbers: is_array($serials) ? array_values(array_filter(array_map('strval', $serials))) : [],
            condition: (string) Arr::get($data, 'condition', 'good'),
        );
    }
}
