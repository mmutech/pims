<?php

namespace App\Domain\Inventory\Models;

use App\Domain\Warehouse\Models\Warehouse;
use App\Domain\Warehouse\Models\WarehouseBin;
use App\Domain\Warehouse\Models\WarehouseLocation;
use App\Enums\StockDimensionType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockDimension extends Model
{
    use HasFactory;

    protected $fillable = [
        'warehouse_id',
        'warehouse_location_id',
        'warehouse_bin_id',
        'code',
        'type',
        'is_available',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => StockDimensionType::class,
            'is_available' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(WarehouseLocation::class, 'warehouse_location_id');
    }

    public function bin(): BelongsTo
    {
        return $this->belongsTo(WarehouseBin::class, 'warehouse_bin_id');
    }

    public function balances(): HasMany
    {
        return $this->hasMany(InventoryBalance::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('is_available', true);
    }

    /**
     * Resolve (or create) the canonical dimension for a physical location.
     */
    public static function resolve(
        int $warehouseId,
        ?int $locationId = null,
        ?int $binId = null,
        StockDimensionType $type = StockDimensionType::Warehouse,
    ): self {
        $code = sprintf(
            'W%d:L%d:B%d:%s',
            $warehouseId,
            $locationId ?? 0,
            $binId ?? 0,
            $type->value,
        );

        return static::query()->firstOrCreate(
            ['code' => $code],
            [
                'warehouse_id' => $warehouseId,
                'warehouse_location_id' => $locationId,
                'warehouse_bin_id' => $binId,
                'type' => $type,
                'is_available' => $type->isAvailableForIssue(),
                'is_active' => true,
            ],
        );
    }
}
