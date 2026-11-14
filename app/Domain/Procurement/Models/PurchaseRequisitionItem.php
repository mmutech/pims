<?php

namespace App\Domain\Procurement\Models;

use App\Domain\Catalog\Models\Item;
use App\Domain\Catalog\Models\UnitOfMeasure;
use App\Support\Decimal;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseRequisitionItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_requisition_id',
        'item_id',
        'unit_of_measure_id',
        'requested_quantity',
        'approved_quantity',
        'converted_quantity',
        'estimated_unit_price',
        'estimated_line_total',
        'required_by',
        'specification',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'requested_quantity' => 'decimal:4',
            'approved_quantity' => 'decimal:4',
            'converted_quantity' => 'decimal:4',
            'estimated_unit_price' => 'decimal:4',
            'estimated_line_total' => 'decimal:2',
            'required_by' => 'date',
            'sort_order' => 'integer',
        ];
    }

    public function purchaseRequisition(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequisition::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function unitOfMeasure(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class);
    }

    public function remainingQuantity(): string
    {
        return Decimal::subtract($this->approved_quantity, $this->converted_quantity, Decimal::QUANTITY_SCALE);
    }
}
