<?php

namespace App\DTOs\Stocktaking;

final readonly class StockCountEntryData
{
    public function __construct(
        public int $stockCountItemId,
        public string $countedQuantity,
        public ?string $notes = null,
    ) {}
}
