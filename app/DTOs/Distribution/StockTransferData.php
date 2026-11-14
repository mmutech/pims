<?php

namespace App\DTOs\Distribution;

use App\Support\Input;
use Illuminate\Support\Arr;

final readonly class StockTransferData
{
    /**
     * @param  list<StockTransferItemData>  $items
     */
    public function __construct(
        public int $sourceWarehouseId,
        public int $destinationWarehouseId,
        public int $requestedBy,
        public string $reason,
        public array $items,
        public ?string $requiredBy = null,
    ) {}

    public static function fromArray(array $data): self
    {
        $items = array_map(
            fn ($item) => $item instanceof StockTransferItemData
                ? $item
                : StockTransferItemData::fromArray((array) $item),
            (array) Arr::get($data, 'items', []),
        );

        return new self(
            sourceWarehouseId: (int) Arr::get($data, 'source_warehouse_id'),
            destinationWarehouseId: (int) Arr::get($data, 'destination_warehouse_id'),
            requestedBy: (int) Arr::get($data, 'requested_by'),
            reason: (string) Arr::get($data, 'reason', ''),
            items: $items,
            requiredBy: Input::stringOrNull(Arr::get($data, 'required_by')),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'source_warehouse_id' => $this->sourceWarehouseId,
            'destination_warehouse_id' => $this->destinationWarehouseId,
            'requested_by' => $this->requestedBy,
            'reason' => $this->reason,
            'required_by' => $this->requiredBy,
        ];
    }
}
