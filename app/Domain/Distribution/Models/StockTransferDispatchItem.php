<?php

namespace App\Domain\Distribution\Models;

use App\Domain\Catalog\Models\Item;
use App\Domain\Inventory\Models\StockDimension;
use App\Domain\Inventory\Models\StockTransactionItem;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockTransferDispatchItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'dispatch_id',
        'stock_transfer_item_id',
        'item_id',
        'quantity',
        'batch_id',
        'serial_numbers',
        'stock_dimension_id',
        'stock_transaction_item_id',
        'received_quantity',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'serial_numbers' => 'array',
            'received_quantity' => 'decimal:4',
        ];
    }

    public function dispatch(): BelongsTo
    {
        return $this->belongsTo(StockTransferDispatch::class, 'dispatch_id');
    }

    public function transferItem(): BelongsTo
    {
        return $this->belongsTo(StockTransferItem::class, 'stock_transfer_item_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function dimension(): BelongsTo
    {
        return $this->belongsTo(StockDimension::class, 'stock_dimension_id');
    }

    public function transactionItem(): BelongsTo
    {
        return $this->belongsTo(StockTransactionItem::class, 'stock_transaction_item_id');
    }
}
