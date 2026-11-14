<?php

namespace App\Domain\Procurement\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderAmendmentItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_order_amendment_id',
        'purchase_order_item_id',
        'original_quantity',
        'amended_quantity',
        'original_unit_price',
        'amended_unit_price',
        'original_line_total',
        'amended_line_total',
    ];

    protected function casts(): array
    {
        return [
            'original_quantity' => 'decimal:4',
            'amended_quantity' => 'decimal:4',
            'original_unit_price' => 'decimal:4',
            'amended_unit_price' => 'decimal:4',
            'original_line_total' => 'decimal:2',
            'amended_line_total' => 'decimal:2',
        ];
    }

    public function amendment(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderAmendment::class, 'purchase_order_amendment_id');
    }

    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }
}
