<?php

namespace App\DTOs\Inventory;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

final readonly class StockReservationData
{
    /**
     * @param  list<StockReservationLineData>  $lines
     */
    public function __construct(
        public Model $source,
        public int $warehouseId,
        public array $lines,
        public int $createdBy,
        public ?string $expiresAt = null,
    ) {}

    public static function fromArray(array $data): self
    {
        $lines = array_map(
            fn ($line) => $line instanceof StockReservationLineData
                ? $line
                : StockReservationLineData::fromArray((array) $line),
            (array) Arr::get($data, 'lines', []),
        );

        return new self(
            source: $data['source'],
            warehouseId: (int) Arr::get($data, 'warehouse_id'),
            lines: $lines,
            createdBy: (int) Arr::get($data, 'created_by'),
            expiresAt: Arr::get($data, 'expires_at'),
        );
    }
}
