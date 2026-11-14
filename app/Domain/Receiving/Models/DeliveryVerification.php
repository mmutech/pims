<?php

namespace App\Domain\Receiving\Models;

use App\Domain\Warehouse\Models\Warehouse;
use App\Enums\DeliveryVerificationStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeliveryVerification extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_number',
        'sra_id',
        'warehouse_id',
        'verified_by',
        'verified_at',
        'status',
        'discrepancy_summary',
    ];

    protected function casts(): array
    {
        return [
            'status' => DeliveryVerificationStatus::class,
            'verified_at' => 'datetime',
        ];
    }

    public function sra(): BelongsTo
    {
        return $this->belongsTo(StoreReceiptAdvice::class, 'sra_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(DeliveryVerificationItem::class, 'verification_id');
    }
}
