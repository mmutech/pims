<?php

namespace App\Domain\Distribution\Models;

use App\Domain\Inventory\Models\StockTransaction;
use App\Domain\Warehouse\Models\Warehouse;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockTransferDispatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_number',
        'stock_transfer_id',
        'source_warehouse_id',
        'dispatched_by',
        'dispatched_at',
        'vehicle_number',
        'driver_name',
        'status',
        'posted_at',
        'posted_by',
        'stock_transaction_id',
    ];

    protected function casts(): array
    {
        return [
            'dispatched_at' => 'datetime',
            'posted_at' => 'datetime',
        ];
    }

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(StockTransfer::class, 'stock_transfer_id');
    }

    public function sourceWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'source_warehouse_id');
    }

    public function dispatchedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispatched_by');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(StockTransaction::class, 'stock_transaction_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockTransferDispatchItem::class, 'dispatch_id');
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(StockTransferReceipt::class, 'dispatch_id');
    }
}
