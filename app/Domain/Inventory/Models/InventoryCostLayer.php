<?php

namespace App\Domain\Inventory\Models;

use App\Domain\Catalog\Models\Item;
use App\Domain\Catalog\Models\ItemBatch;
use App\Domain\Warehouse\Models\Warehouse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryCostLayer extends Model
{
    use HasFactory;

    protected $fillable = [
        'item_id',
        'warehouse_id',
        'stock_dimension_id',
        'batch_id',
        'stock_transaction_item_id',
        'original_quantity',
        'remaining_quantity',
        'unit_cost',
        'received_at',
        'sequence',
    ];

    protected function casts(): array
    {
        return [
            'original_quantity' => 'decimal:4',
            'remaining_quantity' => 'decimal:4',
            'unit_cost' => 'decimal:6',
            'received_at' => 'datetime',
            'sequence' => 'integer',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function dimension(): BelongsTo
    {
        return $this->belongsTo(StockDimension::class, 'stock_dimension_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ItemBatch::class, 'batch_id');
    }

    public function transactionItem(): BelongsTo
    {
        return $this->belongsTo(StockTransactionItem::class, 'stock_transaction_item_id');
    }

    public function scopeRemaining(Builder $query): Builder
    {
        return $query->where('remaining_quantity', '>', 0);
    }

    public function scopeFifo(Builder $query): Builder
    {
        return $query->orderBy('received_at')->orderBy('sequence')->orderBy('id');
    }
}
