<?php

namespace App\Domain\Inventory\Models;

use App\Domain\Catalog\Models\Item;
use App\Domain\Catalog\Models\ItemBatch;
use App\Domain\Catalog\Models\ItemSerialNumber;
use App\Domain\Warehouse\Models\Warehouse;
use App\Enums\StockMovementType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only stock ledger. Rows are never updated or deleted; corrections are
 * posted as reversal entries.
 */
class StockLedger extends Model
{
    use HasFactory;

    protected $table = 'stock_ledger';

    public $timestamps = false;

    protected $fillable = [
        'stock_transaction_id',
        'stock_transaction_item_id',
        'warehouse_id',
        'stock_dimension_id',
        'item_id',
        'batch_id',
        'serial_number_id',
        'movement_type',
        'quantity_in',
        'quantity_out',
        'unit_cost',
        'value_in',
        'value_out',
        'balance_quantity_after',
        'balance_value_after',
        'posted_at',
        'posted_by',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'movement_type' => StockMovementType::class,
            'quantity_in' => 'decimal:4',
            'quantity_out' => 'decimal:4',
            'unit_cost' => 'decimal:6',
            'value_in' => 'decimal:2',
            'value_out' => 'decimal:2',
            'balance_quantity_after' => 'decimal:4',
            'balance_value_after' => 'decimal:2',
            'posted_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(StockTransaction::class, 'stock_transaction_id');
    }

    public function transactionItem(): BelongsTo
    {
        return $this->belongsTo(StockTransactionItem::class, 'stock_transaction_item_id');
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

    public function serialNumber(): BelongsTo
    {
        return $this->belongsTo(ItemSerialNumber::class, 'serial_number_id');
    }

    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }
}
