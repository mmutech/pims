<?php

namespace App\Domain\Distribution\Models;

use App\Domain\Catalog\Models\Item;
use App\Domain\Catalog\Models\UnitOfMeasure;
use App\Support\Decimal;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaterialRequisitionItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'material_requisition_id',
        'item_id',
        'unit_of_measure_id',
        'requested_quantity',
        'approved_quantity',
        'reserved_quantity',
        'issued_quantity',
        'outstanding_quantity',
        'notes',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'requested_quantity' => 'decimal:4',
            'approved_quantity' => 'decimal:4',
            'reserved_quantity' => 'decimal:4',
            'issued_quantity' => 'decimal:4',
            'outstanding_quantity' => 'decimal:4',
            'sort_order' => 'integer',
        ];
    }

    public function requisition(): BelongsTo
    {
        return $this->belongsTo(MaterialRequisition::class, 'material_requisition_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function unitOfMeasure(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class);
    }

    public function backorders(): HasMany
    {
        return $this->hasMany(MaterialBackorder::class);
    }

    public function issueItems(): HasMany
    {
        return $this->hasMany(StockIssueItem::class);
    }

    /**
     * Approved quantity that has not yet been reserved or issued.
     */
    public function unreservedQuantity(): string
    {
        return Decimal::subtract($this->outstanding_quantity, $this->reserved_quantity, Decimal::QUANTITY_SCALE);
    }
}
