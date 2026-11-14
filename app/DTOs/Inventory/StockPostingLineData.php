<?php

namespace App\DTOs\Inventory;

final readonly class StockPostingLineData
{
    public function __construct(
        public int $itemId,
        public int $unitOfMeasureId,
        public int $stockDimensionId,
        public string $quantity,
        public string $unitCost = '0',
        public ?int $batchId = null,
        public ?int $serialNumberId = null,
        public ?string $reference = null,
        public ?int $reservationItemId = null,
    ) {}
}
