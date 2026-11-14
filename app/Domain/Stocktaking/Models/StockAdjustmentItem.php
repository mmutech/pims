<?php

namespace App\Domain\Stocktaking\Models;

use App\Domain\Catalog\Models\Item;
use App\Domain\Catalog\Models\ItemBatch;
use App\Domain\Catalog\Models\ItemSerialNumber;
use App\Domain\Catalog\Models\UnitOfMeasure;
use App\Domain\Inventory\Models\StockDimension;
use App\Domain\Inventory\Models\StockTransactionItem;
use App\Support\Decimal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockAdjustmentItem extends Model
{
    protected $fillable = [
        'stock_adjustment_id',
        'item_id',
        'unit_of_measure_id',
        'stock_dimension_id',
        'stock_count_item_id',
        'batch_id',
        'serial_number_id',
        'quantity_delta',
        'unit_cost',
        'value_delta',
        'reason',
        'stock_transaction_item_id',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'quantity_delta' => 'decimal:4',
            'unit_cost' => 'decimal:6',
            'value_delta' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    public function adjustment(): BelongsTo
    {
        return $this->belongsTo(StockAdjustment::class, 'stock_adjustment_id');
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

    public function stockCountItem(): BelongsTo
    {
        return $this->belongsTo(StockCountItem::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ItemBatch::class);
    }

    public function serialNumber(): BelongsTo
    {
        return $this->belongsTo(ItemSerialNumber::class);
    }

    public function transactionItem(): BelongsTo
    {
        return $this->belongsTo(StockTransactionItem::class, 'stock_transaction_item_id');
    }

    public function isIncrease(): bool
    {
        return ! Decimal::isNegative($this->quantity_delta);
    }

    public function absoluteQuantity(): string
    {
        $quantity = Decimal::normalize($this->quantity_delta);

        return Decimal::isNegative($quantity)
            ? Decimal::subtract('0', $quantity)
            : $quantity;
    }
}
