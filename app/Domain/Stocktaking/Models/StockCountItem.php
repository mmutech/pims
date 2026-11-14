<?php

namespace App\Domain\Stocktaking\Models;

use App\Domain\Catalog\Models\Item;
use App\Domain\Catalog\Models\ItemBatch;
use App\Domain\Catalog\Models\ItemSerialNumber;
use App\Domain\Inventory\Models\StockDimension;
use App\Models\User;
use App\Support\Decimal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class StockCountItem extends Model
{
    protected $fillable = [
        'stock_count_id',
        'item_id',
        'stock_dimension_id',
        'batch_id',
        'serial_number_id',
        'system_quantity',
        'counted_quantity',
        'variance_quantity',
        'system_unit_cost',
        'system_value',
        'counted_by',
        'counted_at',
        'is_recount',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'system_quantity' => 'decimal:4',
            'counted_quantity' => 'decimal:4',
            'variance_quantity' => 'decimal:4',
            'system_unit_cost' => 'decimal:6',
            'system_value' => 'decimal:2',
            'counted_at' => 'datetime',
            'is_recount' => 'boolean',
        ];
    }

    public function stockCount(): BelongsTo
    {
        return $this->belongsTo(StockCount::class);
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
        return $this->belongsTo(ItemBatch::class);
    }

    public function serialNumber(): BelongsTo
    {
        return $this->belongsTo(ItemSerialNumber::class);
    }

    public function countedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'counted_by');
    }

    public function recounts(): HasMany
    {
        return $this->hasMany(StockCountRecount::class)->orderBy('recount_number');
    }

    public function variance(): HasOne
    {
        return $this->hasOne(StockCountVariance::class);
    }

    public function isCounted(): bool
    {
        return $this->counted_quantity !== null;
    }

    /**
     * Counted quantity minus the expected quantity (system snapshot adjusted
     * for movements recorded after the snapshot).
     */
    public function calculateVariance(string $expectedQuantity): string
    {
        if ($this->counted_quantity === null) {
            return '0';
        }

        return Decimal::subtract(
            $this->counted_quantity,
            $expectedQuantity,
            Decimal::QUANTITY_SCALE,
        );
    }
}
