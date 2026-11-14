<?php

namespace App\DTOs\Distribution;

use App\Support\Input;
use Illuminate\Support\Arr;

final readonly class StockTransferDispatchLineData
{
    /**
     * @param  list<string>  $serialNumbers
     */
    public function __construct(
        public int $stockTransferItemId,
        public string $quantity,
        public ?int $batchId = null,
        public array $serialNumbers = [],
    ) {}

    public static function fromArray(array $data): self
    {
        $serials = Arr::get($data, 'serial_numbers', []);

        return new self(
            stockTransferItemId: (int) Arr::get($data, 'stock_transfer_item_id'),
            quantity: (string) Arr::get($data, 'quantity', '0'),
            batchId: Input::intOrNull(Arr::get($data, 'batch_id')),
            serialNumbers: is_array($serials) ? array_values($serials) : [],
        );
    }
}
