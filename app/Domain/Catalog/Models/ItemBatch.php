<?php

namespace App\Domain\Catalog\Models;

use App\Domain\Supplier\Models\Supplier;
use App\Enums\BatchStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ItemBatch extends Model
{
    protected $fillable = [
        'item_id',
        'batch_number',
        'manufacture_date',
        'expiry_date',
        'supplier_id',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'manufacture_date' => 'date',
            'expiry_date' => 'date',
            'status' => BatchStatus::class,
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function serialNumbers(): HasMany
    {
        return $this->hasMany(ItemSerialNumber::class, 'batch_id');
    }

    public function isExpired(): bool
    {
        return $this->expiry_date !== null && $this->expiry_date->isPast();
    }
}
