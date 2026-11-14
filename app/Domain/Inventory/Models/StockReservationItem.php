<?php

namespace App\Domain\Inventory\Models;

use App\Domain\Catalog\Models\Item;
use App\Domain\Catalog\Models\ItemBatch;
use App\Support\Decimal;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockReservationItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'stock_reservation_id',
        'item_id',
        'stock_dimension_id',
        'batch_id',
        'quantity',
        'consumed_quantity',
        'released_quantity',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'consumed_quantity' => 'decimal:4',
            'released_quantity' => 'decimal:4',
        ];
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(StockReservation::class, 'stock_reservation_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function dimension(): BelongsTo
    {
        return $this->belongsTo(StockDimension::class, 'stock_dimension_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ItemBatch::class, 'batch_id');
    }

    public function outstandingQuantity(): string
    {
        return Decimal::subtract(
            Decimal::subtract((string) $this->quantity, (string) $this->consumed_quantity, Decimal::QUANTITY_SCALE),
            (string) $this->released_quantity,
            Decimal::QUANTITY_SCALE,
        );
    }

    public function isFullyResolved(): bool
    {
        return ! Decimal::isPositive($this->outstandingQuantity());
    }
}
