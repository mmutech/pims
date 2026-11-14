<?php

namespace App\Domain\Receiving\Models;

use App\Domain\Catalog\Models\Item;
use App\Domain\Catalog\Models\UnitOfMeasure;
use App\Domain\Procurement\Models\PurchaseOrderItem;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreReceiptAdviceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'sra_id',
        'purchase_order_item_id',
        'item_id',
        'unit_of_measure_id',
        'expected_quantity',
        'delivered_quantity',
        'batch_number',
        'serial_numbers',
        'condition_notes',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'expected_quantity' => 'decimal:4',
            'delivered_quantity' => 'decimal:4',
            'serial_numbers' => 'array',
            'sort_order' => 'integer',
        ];
    }

    public function sra(): BelongsTo
    {
        return $this->belongsTo(StoreReceiptAdvice::class, 'sra_id');
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

    public function verificationItem(): BelongsTo
    {
        return $this->belongsTo(DeliveryVerificationItem::class, 'id', 'sra_item_id');
    }
}
