<?php

namespace App\DTOs\Inventory;

use App\Enums\StockMovementType;
use Illuminate\Database\Eloquent\Model;

final readonly class StockPostingData
{
    /**
     * @param  list<StockPostingLineData>  $lines
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public StockMovementType $movementType,
        public Model $source,
        public string $operation,
        public int $warehouseId,
        public int $postedBy,
        public array $lines,
        public ?int $destinationWarehouseId = null,
        public ?string $postedAt = null,
        public array $metadata = [],
        public ?string $transactionNumber = null,
    ) {}

    public function idempotencyKey(): string
    {
        return sprintf(
            '%s:%s:%s',
            $this->source->getMorphClass(),
            $this->source->getKey(),
            $this->operation,
        );
    }
}
