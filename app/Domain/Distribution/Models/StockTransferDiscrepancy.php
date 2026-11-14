<?php

namespace App\Domain\Distribution\Models;

use App\Enums\TransferDiscrepancyResolution;
use App\Enums\TransferDiscrepancyType;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockTransferDiscrepancy extends Model
{
    use HasFactory;

    protected $fillable = [
        'stock_transfer_id',
        'dispatch_item_id',
        'variance_type',
        'quantity',
        'resolution',
        'resolved_by',
        'resolved_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'variance_type' => TransferDiscrepancyType::class,
            'quantity' => 'decimal:4',
            'resolution' => TransferDiscrepancyResolution::class,
            'resolved_at' => 'datetime',
        ];
    }

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(StockTransfer::class, 'stock_transfer_id');
    }

    public function dispatchItem(): BelongsTo
    {
        return $this->belongsTo(StockTransferDispatchItem::class, 'dispatch_item_id');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('resolution', TransferDiscrepancyResolution::Pending->value);
    }
}
