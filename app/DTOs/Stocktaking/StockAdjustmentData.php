<?php

namespace App\DTOs\Stocktaking;

use App\Enums\AdjustmentReason;
use App\Enums\AdjustmentType;

final readonly class StockAdjustmentData
{
    /**
     * @param  list<StockAdjustmentItemData>  $items
     */
    public function __construct(
        public int $warehouseId,
        public AdjustmentType $adjustmentType,
        public AdjustmentReason $reasonCode,
        public string $reason,
        public array $items,
        public ?int $stockCountId = null,
        public bool $autoSubmit = false,
    ) {}
}
