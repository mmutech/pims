<?php

namespace App\Domain\Distribution\Models;

use App\Domain\Inventory\Models\StockDimension;
use App\Domain\Inventory\Models\StockTransactionItem;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockTransferReceiptItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'receipt_id',
        'dispatch_item_id',
        'received_quantity',
        'damaged_quantity',
        'missing_quantity',
        'batch_id',
        'stock_dimension_id',
        'stock_transaction_item_id',
    ];

    protected function casts(): array
    {
        return [
            'received_quantity' => 'decimal:4',
            'damaged_quantity' => 'decimal:4',
            'missing_quantity' => 'decimal:4',
        ];
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(StockTransferReceipt::class, 'receipt_id');
    }

    public function dispatchItem(): BelongsTo
    {
        return $this->belongsTo(StockTransferDispatchItem::class, 'dispatch_item_id');
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
