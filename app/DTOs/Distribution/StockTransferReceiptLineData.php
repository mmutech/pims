<?php

namespace App\DTOs\Distribution;

use Illuminate\Support\Arr;

final readonly class StockTransferReceiptLineData
{
    public function __construct(
        public int $dispatchItemId,
        public string $receivedQuantity,
        public string $damagedQuantity = '0',
        public string $missingQuantity = '0',
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            dispatchItemId: (int) Arr::get($data, 'dispatch_item_id'),
            receivedQuantity: (string) Arr::get($data, 'received_quantity', '0'),
            damagedQuantity: (string) Arr::get($data, 'damaged_quantity', '0'),
            missingQuantity: (string) Arr::get($data, 'missing_quantity', '0'),
        );
    }
}
