<?php

namespace App\Domain\Distribution\Models;

use App\Domain\Inventory\Models\StockTransaction;
use App\Domain\Supplier\Models\Supplier;
use App\Domain\Warehouse\Models\Warehouse;
use App\Enums\MaterialReturnType;
use App\Enums\ReturnStatus;
use App\Models\User;
use App\Support\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MaterialReturn extends Model
{
    use HasFactory, RecordsActivity, SoftDeletes;

    protected $fillable = [
        'document_number',
        'return_type',
        'original_issue_id',
        'original_grn_id',
        'supplier_id',
        'warehouse_id',
        'returned_by',
        'received_by',
        'reason',
        'status',
        'posted_at',
        'posted_by',
        'stock_transaction_id',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'return_type' => MaterialReturnType::class,
            'status' => ReturnStatus::class,
            'posted_at' => 'datetime',
        ];
    }

    public function originalIssue(): BelongsTo
    {
        return $this->belongsTo(StockIssue::class, 'original_issue_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function returnedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'returned_by');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(StockTransaction::class, 'stock_transaction_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(MaterialReturnItem::class)->orderBy('sort_order')->orderBy('id');
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

            $query->orWhere('returned_by', $user->id);
        });
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($term): void {
            $query->where('document_number', 'like', "%{$term}%")
                ->orWhere('reason', 'like', "%{$term}%");
        });
    }

    public function canBeEdited(): bool
    {
        return in_array($this->status, [ReturnStatus::Draft, ReturnStatus::PendingInspection], true);
    }

    public function canBeAssessed(): bool
    {
        return in_array($this->status, [ReturnStatus::Draft, ReturnStatus::PendingInspection], true);
    }

    public function canBePosted(): bool
    {
        return $this->status === ReturnStatus::Assessed;
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, [
            ReturnStatus::Draft,
            ReturnStatus::PendingInspection,
            ReturnStatus::Assessed,
            ReturnStatus::Rejected,
        ], true);
    }
}
