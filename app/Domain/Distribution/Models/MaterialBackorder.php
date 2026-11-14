<?php

namespace App\Domain\Distribution\Models;

use App\Domain\Warehouse\Models\Warehouse;
use App\Enums\MaterialBackorderStatus;
use App\Support\Decimal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialBackorder extends Model
{
    use HasFactory;

    protected $fillable = [
        'material_requisition_item_id',
        'warehouse_id',
        'outstanding_quantity',
        'status',
        'expected_date',
    ];

    protected function casts(): array
    {
        return [
            'outstanding_quantity' => 'decimal:4',
            'status' => MaterialBackorderStatus::class,
            'expected_date' => 'date',
        ];
    }

    public function requisitionItem(): BelongsTo
    {
        return $this->belongsTo(MaterialRequisitionItem::class, 'material_requisition_item_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', MaterialBackorderStatus::Open->value);
    }

    public function outstandingQuantity(): string
    {
        return Decimal::normalize($this->outstanding_quantity, Decimal::QUANTITY_SCALE);
    }
}
