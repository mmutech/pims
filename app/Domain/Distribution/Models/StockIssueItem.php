<?php

namespace App\Domain\Distribution\Models;

use App\Domain\Catalog\Models\Item;
use App\Domain\Catalog\Models\ItemBatch;
use App\Domain\Catalog\Models\UnitOfMeasure;
use App\Domain\Inventory\Models\StockDimension;
use App\Domain\Inventory\Models\StockReservationItem;
use App\Domain\Inventory\Models\StockTransactionItem;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockIssueItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'stock_issue_id',
        'material_requisition_item_id',
        'item_id',
        'unit_of_measure_id',
        'stock_reservation_item_id',
        'issued_quantity',
        'unit_cost',
        'total_cost',
        'batch_id',
        'serial_number_id',
        'stock_dimension_id',
        'stock_transaction_item_id',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'issued_quantity' => 'decimal:4',
            'unit_cost' => 'decimal:6',
            'total_cost' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    public function issue(): BelongsTo
    {
        return $this->belongsTo(StockIssue::class, 'stock_issue_id');
    }

    public function requisitionItem(): BelongsTo
    {
        return $this->belongsTo(MaterialRequisitionItem::class, 'material_requisition_item_id');
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
        return $this->belongsTo(ItemBatch::class);
    }

    public function reservationItem(): BelongsTo
    {
        return $this->belongsTo(StockReservationItem::class, 'stock_reservation_item_id');
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
