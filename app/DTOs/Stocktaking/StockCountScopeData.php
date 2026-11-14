<?php

namespace App\DTOs\Stocktaking;

final readonly class StockCountScopeData
{
    public function __construct(
        public ?int $warehouseLocationId = null,
        public ?int $itemCategoryId = null,
        public ?int $itemId = null,
        public bool $includeZeroBalances = false,
    ) {}
}
