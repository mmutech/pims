<?php

namespace App\DTOs\Stocktaking;

use App\Support\Decimal;

final readonly class StockAdjustmentItemData
{
    public function __construct(
        public int $itemId,
        public int $unitOfMeasureId,
        public int $stockDimensionId,
        public string $quantityDelta,
        public string $unitCost = '0',
        public ?int $batchId = null,
        public ?int $serialNumberId = null,
        public ?int $stockCountItemId = null,
        public ?string $reason = null,
    ) {}

    public function absoluteQuantity(): string
    {
        $quantity = Decimal::normalize($this->quantityDelta);

        return Decimal::isNegative($quantity)
            ? Decimal::subtract('0', $quantity)
            : $quantity;
    }

    public function isIncrease(): bool
    {
        return ! Decimal::isNegative($this->quantityDelta);
    }
}
