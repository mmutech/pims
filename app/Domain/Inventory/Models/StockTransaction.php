<?php

namespace App\Domain\Inventory\Models;

use App\Domain\Warehouse\Models\Warehouse;
use App\Enums\StockMovementType;
use App\Enums\StockTransactionStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class StockTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_number',
        'transaction_type',
        'source_type',
        'source_id',
        'idempotency_key',
        'warehouse_id',
        'destination_warehouse_id',
        'status',
        'posted_by',
        'posted_at',
        'reversed_by_transaction_id',
        'reversal_reason',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'transaction_type' => StockMovementType::class,
            'status' => StockTransactionStatus::class,
            'posted_at' => 'datetime',
            'metadata' => 'array',
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

    public function destinationWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'destination_warehouse_id');
    }

    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockTransactionItem::class)->orderBy('id');
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(StockLedger::class)->orderBy('id');
    }

    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversed_by_transaction_id');
    }

    public function isPosted(): bool
    {
        return $this->status === StockTransactionStatus::Posted;
    }

    public function isReversed(): bool
    {
        return $this->status === StockTransactionStatus::Reversed;
    }

    /**
     * Human-readable source reference for lists and audit screens.
     */
    public function sourceLabel(): string
    {
        $source = $this->source;

        if ($source !== null && isset($source->document_number) && $source->document_number !== '') {
            return (string) $source->document_number;
        }

        return class_basename($this->source_type).' #'.$this->source_id;
    }
}
