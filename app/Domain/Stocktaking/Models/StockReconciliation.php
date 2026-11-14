<?php

namespace App\Domain\Stocktaking\Models;

use App\Domain\Warehouse\Models\Warehouse;
use App\Enums\ReconciliationStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockReconciliation extends Model
{
    protected $fillable = [
        'document_number',
        'stock_count_id',
        'warehouse_id',
        'snapshot_quantity',
        'movement_quantity',
        'adjustment_quantity',
        'final_quantity',
        'system_quantity',
        'difference_quantity',
        'status',
        'notes',
        'prepared_by',
        'approved_by',
        'approved_at',
        'posted_at',
        'approval_request_id',
    ];

    protected function casts(): array
    {
        return [
            'snapshot_quantity' => 'decimal:4',
            'movement_quantity' => 'decimal:4',
            'adjustment_quantity' => 'decimal:4',
            'final_quantity' => 'decimal:4',
            'system_quantity' => 'decimal:4',
            'difference_quantity' => 'decimal:4',
            'status' => ReconciliationStatus::class,
            'approved_at' => 'datetime',
            'posted_at' => 'datetime',
        ];
    }

    public function stockCount(): BelongsTo
    {
        return $this->belongsTo(StockCount::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function preparedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasGlobalAccess()) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($user): void {
            $warehouseIds = $user->activeWarehouseIds();

            if ($warehouseIds !== []) {
                $query->whereIn('warehouse_id', $warehouseIds);
            }

            $query->orWhere('prepared_by', $user->id);
        });
    }

    public function canBeApproved(): bool
    {
        return $this->status->canBeApproved();
    }

    public function canBePosted(): bool
    {
        return $this->status->canBePosted();
    }
}
