<?php

namespace App\DTOs\Stocktaking;

use App\Enums\CountType;

final readonly class StockCountData
{
    /**
     * @param  list<StockCountScopeData>  $scopes
     */
    public function __construct(
        public int $warehouseId,
        public CountType $countType,
        public string $scopeDescription,
        public array $scopes = [],
        public string $toleranceQuantity = '0',
        public string $toleranceValue = '0',
    ) {}
}
