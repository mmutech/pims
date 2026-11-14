<?php

namespace App\Domain\Receiving\Models;

use App\Enums\DeliveryCondition;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryVerificationItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'verification_id',
        'sra_item_id',
        'verified_quantity',
        'damaged_quantity',
        'short_quantity',
        'condition',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'verified_quantity' => 'decimal:4',
            'damaged_quantity' => 'decimal:4',
            'short_quantity' => 'decimal:4',
            'condition' => DeliveryCondition::class,
        ];
    }

    public function verification(): BelongsTo
    {
        return $this->belongsTo(DeliveryVerification::class, 'verification_id');
    }

    public function sraItem(): BelongsTo
    {
        return $this->belongsTo(StoreReceiptAdviceItem::class, 'sra_item_id');
    }
}
