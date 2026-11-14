<?php

namespace App\Domain\Inventory\Models;

use App\Domain\Warehouse\Models\Warehouse;
use App\Enums\StockReservationStatus;
use App\Models\User;
use App\Support\Decimal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class StockReservation extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_number',
        'source_type',
        'source_id',
        'warehouse_id',
        'status',
        'release_reason',
        'expires_at',
        'closed_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => StockReservationStatus::class,
            'expires_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockReservationItem::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [
            StockReservationStatus::Active->value,
            StockReservationStatus::PartiallyConsumed->value,
        ]);
    }

    public function isOpen(): bool
    {
        return $this->status->isOpen();
    }

    public function reservedQuantity(): string
    {
        return $this->items->reduce(
            fn (string $carry, StockReservationItem $item): string => Decimal::add(
                $carry,
                (string) $item->quantity,
                Decimal::QUANTITY_SCALE,
            ),
            Decimal::normalize('0'),
        );
    }

    public function outstandingQuantity(): string
    {
        return $this->items->reduce(
            fn (string $carry, StockReservationItem $item): string => Decimal::add(
                $carry,
                $item->outstandingQuantity(),
                Decimal::QUANTITY_SCALE,
            ),
            Decimal::normalize('0'),
        );
    }
}
