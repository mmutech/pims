<?php

namespace App\DTOs\Distribution;

use App\Support\Input;
use Illuminate\Support\Arr;

final readonly class StockTransferReceiptData
{
    /**
     * @param  list<StockTransferReceiptLineData>  $lines
     */
    public function __construct(
        public int $dispatchId,
        public int $receivedBy,
        public array $lines,
        public ?string $receivedAt = null,
        public ?string $notes = null,
    ) {}

    public static function fromArray(array $data): self
    {
        $lines = array_map(
            fn ($line) => $line instanceof StockTransferReceiptLineData
                ? $line
                : StockTransferReceiptLineData::fromArray((array) $line),
            (array) Arr::get($data, 'lines', []),
        );

        return new self(
            dispatchId: (int) Arr::get($data, 'dispatch_id'),
            receivedBy: (int) Arr::get($data, 'received_by'),
            lines: $lines,
            receivedAt: Input::stringOrNull(Arr::get($data, 'received_at')),
            notes: Input::stringOrNull(Arr::get($data, 'notes')),
        );
    }
}
