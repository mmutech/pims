<?php

namespace App\Domain\Receiving\Models;

use App\Domain\Catalog\Models\Item;
use App\Domain\Catalog\Models\ItemBatch;
use App\Domain\Catalog\Models\UnitOfMeasure;
use App\Domain\Inventory\Models\StockTransactionItem;
use App\Domain\Procurement\Models\PurchaseOrderItem;
use App\Domain\Warehouse\Models\WarehouseBin;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoodsReceivedNoteItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'grn_id',
        'purchase_order_item_id',
        'item_id',
        'unit_of_measure_id',
        'accepted_quantity',
        'rejected_quantity',
        'unit_cost',
        'batch_id',
        'warehouse_bin_id',
        'stock_transaction_item_id',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'accepted_quantity' => 'decimal:4',
            'rejected_quantity' => 'decimal:4',
            'unit_cost' => 'decimal:6',
            'sort_order' => 'integer',
        ];
    }

    public function grn(): BelongsTo
    {
        return $this->belongsTo(GoodsReceivedNote::class, 'grn_id');
    }

    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class);
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

    public function warehouseBin(): BelongsTo
    {
        return $this->belongsTo(WarehouseBin::class);
    }

    public function stockTransactionItem(): BelongsTo
    {
        return $this->belongsTo(StockTransactionItem::class);
    }
}
