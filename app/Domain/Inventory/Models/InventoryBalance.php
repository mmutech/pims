<?php

namespace App\Domain\Inventory\Models;

use App\Domain\Catalog\Models\Item;
use App\Domain\Catalog\Models\ItemBatch;
use App\Domain\Warehouse\Models\Warehouse;
use App\Support\Decimal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryBalance extends Model
{
    use HasFactory;

    protected $fillable = [
        'warehouse_id',
        'stock_dimension_id',
        'item_id',
        'batch_id',
        'batch_key',
        'on_hand_quantity',
        'reserved_quantity',
        'available_quantity',
        'average_unit_cost',
        'total_value',
        'last_movement_at',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'on_hand_quantity' => 'decimal:4',
            'reserved_quantity' => 'decimal:4',
            'available_quantity' => 'decimal:4',
            'average_unit_cost' => 'decimal:6',
            'total_value' => 'decimal:2',
            'last_movement_at' => 'datetime',
            'version' => 'integer',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function dimension(): BelongsTo
    {
        return $this->belongsTo(StockDimension::class, 'stock_dimension_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ItemBatch::class, 'batch_id');
    }

    public function scopeForWarehouse(Builder $query, int $warehouseId): Builder
    {
        return $query->where('warehouse_id', $warehouseId);
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('available_quantity', '>', 0);
    }

    public function availableAfterReservation(): string
    {
        return Decimal::subtract(
            $this->on_hand_quantity,
            $this->reserved_quantity,
            Decimal::QUANTITY_SCALE,
        );
    }
}
