<?php

namespace App\Domain\Receiving\Models;

use App\Domain\Catalog\Models\Item;
use App\Domain\Catalog\Models\ItemBatch;
use App\Domain\Catalog\Models\UnitOfMeasure;
use App\Domain\Inventory\Models\StockTransactionItem;
use App\Enums\DeliveryCondition;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierReturnItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'supplier_return_id',
        'item_id',
        'unit_of_measure_id',
        'quantity',
        'unit_cost',
        'batch_id',
        'serial_numbers',
        'condition',
        'stock_transaction_item_id',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_cost' => 'decimal:6',
            'serial_numbers' => 'array',
            'condition' => DeliveryCondition::class,
            'sort_order' => 'integer',
        ];
    }

    public function supplierReturn(): BelongsTo
    {
        return $this->belongsTo(SupplierReturn::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function unitOfMeasure(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ItemBatch::class, 'batch_id');
    }

    public function stockTransactionItem(): BelongsTo
    {
        return $this->belongsTo(StockTransactionItem::class);
    }
}
