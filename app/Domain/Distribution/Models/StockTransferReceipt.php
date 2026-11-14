<?php

namespace App\Domain\Distribution\Models;

use App\Domain\Inventory\Models\StockTransaction;
use App\Domain\Warehouse\Models\Warehouse;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockTransferReceipt extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_number',
        'stock_transfer_id',
        'dispatch_id',
        'destination_warehouse_id',
        'received_by',
        'received_at',
        'status',
        'posted_at',
        'posted_by',
        'stock_transaction_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
            'posted_at' => 'datetime',
        ];
    }

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(StockTransfer::class, 'stock_transfer_id');
    }

    public function dispatch(): BelongsTo
    {
        return $this->belongsTo(StockTransferDispatch::class, 'dispatch_id');
    }

    public function destinationWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'destination_warehouse_id');
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(StockTransaction::class, 'stock_transaction_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockTransferReceiptItem::class, 'receipt_id');
    }
}
