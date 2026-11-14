<?php

namespace App\DTOs\Distribution;

use App\Support\Input;
use Illuminate\Support\Arr;

final readonly class StockTransferDispatchData
{
    /**
     * @param  list<StockTransferDispatchLineData>  $lines
     */
    public function __construct(
        public int $stockTransferId,
        public int $dispatchedBy,
        public array $lines,
        public ?string $dispatchedAt = null,
        public ?string $vehicleNumber = null,
        public ?string $driverName = null,
    ) {}

    public static function fromArray(array $data): self
    {
        $lines = array_map(
            fn ($line) => $line instanceof StockTransferDispatchLineData
                ? $line
                : StockTransferDispatchLineData::fromArray((array) $line),
            (array) Arr::get($data, 'lines', []),
        );

        return new self(
            stockTransferId: (int) Arr::get($data, 'stock_transfer_id'),
            dispatchedBy: (int) Arr::get($data, 'dispatched_by'),
            lines: $lines,
            dispatchedAt: Input::stringOrNull(Arr::get($data, 'dispatched_at')),
            vehicleNumber: Input::stringOrNull(Arr::get($data, 'vehicle_number')),
            driverName: Input::stringOrNull(Arr::get($data, 'driver_name')),
        );
    }
}
