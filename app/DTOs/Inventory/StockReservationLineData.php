<?php

namespace App\DTOs\Inventory;

use Illuminate\Support\Arr;

final readonly class StockReservationLineData
{
    public function __construct(
        public int $itemId,
        public int $stockDimensionId,
        public string $quantity,
        public ?int $batchId = null,
        public ?string $reference = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            itemId: (int) Arr::get($data, 'item_id'),
            stockDimensionId: (int) Arr::get($data, 'stock_dimension_id'),
            quantity: (string) Arr::get($data, 'quantity', '0'),
            batchId: Arr::get($data, 'batch_id') !== null ? (int) Arr::get($data, 'batch_id') : null,
            reference: Arr::get($data, 'reference'),
        );
    }
}
