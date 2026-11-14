<?php

namespace App\Domain\Stocktaking\Models;

use App\Domain\Catalog\Models\Item;
use App\Domain\Catalog\Models\ItemCategory;
use App\Domain\Warehouse\Models\WarehouseLocation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockCountScope extends Model
{
    protected $fillable = [
        'stock_count_id',
        'warehouse_location_id',
        'item_category_id',
        'item_id',
        'include_zero_balances',
    ];

    protected function casts(): array
    {
        return [
            'include_zero_balances' => 'boolean',
        ];
    }

    public function stockCount(): BelongsTo
    {
        return $this->belongsTo(StockCount::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(WarehouseLocation::class, 'warehouse_location_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ItemCategory::class, 'item_category_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
