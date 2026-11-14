<?php

namespace App\Domain\Inventory\Models;

use App\Domain\Catalog\Models\Item;
use App\Domain\Catalog\Models\ItemBatch;
use App\Domain\Catalog\Models\ItemSerialNumber;
use App\Domain\Catalog\Models\UnitOfMeasure;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockTransactionItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'stock_transaction_id',
        'item_id',
        'unit_of_measure_id',
        'quantity',
        'unit_cost',
        'total_cost',
        'stock_dimension_id',
        'batch_id',
        'serial_number_id',
        'direction',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_cost' => 'decimal:6',
            'total_cost' => 'decimal:2',
            'direction' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(StockTransaction::class, 'stock_transaction_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function unitOfMeasure(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class);
    }

    public function dimension(): BelongsTo
    {
        return $this->belongsTo(StockDimension::class, 'stock_dimension_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ItemBatch::class, 'batch_id');
    }

    public function serialNumber(): BelongsTo
    {
        return $this->belongsTo(ItemSerialNumber::class, 'serial_number_id');
    }
}
