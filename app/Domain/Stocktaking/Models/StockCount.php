<?php

namespace App\Domain\Stocktaking\Models;

use App\Domain\Warehouse\Models\Warehouse;
use App\Domain\Workflow\Models\ApprovalRequest;
use App\Enums\CountStatus;
use App\Enums\CountType;
use App\Models\User;
use App\Support\Decimal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockCount extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'document_number',
        'warehouse_id',
        'count_type',
        'scope_description',
        'status',
        'tolerance_quantity',
        'tolerance_value',
        'snapshot_at',
        'snapshot_ledger_id',
        'started_at',
        'completed_at',
        'created_by',
        'approved_by',
        'approved_at',
        'approval_request_id',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'count_type' => CountType::class,
            'status' => CountStatus::class,
            'tolerance_quantity' => 'decimal:4',
            'tolerance_value' => 'decimal:2',
            'snapshot_at' => 'datetime',
            'snapshot_ledger_id' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'approved_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function scopes(): HasMany
    {
        return $this->hasMany(StockCountScope::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockCountItem::class)->orderBy('id');
    }

    public function variances(): HasMany
    {
        return $this->hasMany(StockCountVariance::class);
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(StockAdjustment::class);
    }

    public function reconciliations(): HasMany
    {
        return $this->hasMany(StockReconciliation::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function approvalRequest(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($term): void {
            $query->where('document_number', 'like', "%{$term}%")
                ->orWhere('scope_description', 'like', "%{$term}%");
        });
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

            $query->orWhere('created_by', $user->id);
        });
    }

    public function canBeStarted(): bool
    {
        return $this->status->canBeStarted();
    }

    public function canBeCounted(): bool
    {
        return $this->status->canBeCounted();
    }

    public function canBeAnalysed(): bool
    {
        return $this->status->canBeAnalysed();
    }

    public function canBeCompleted(): bool
    {
        return $this->status === CountStatus::AdjustmentPending;
    }

    public function canBeCancelled(): bool
    {
        return $this->status->canBeCancelled();
    }

    public function isBlind(): bool
    {
        return $this->count_type->isBlind();
    }

    public function hidesSystemQuantity(): bool
    {
        return $this->count_type->hidesSystemQuantity();
    }

    public function hasOpenVariances(): bool
    {
        return $this->variances()->where('resolution', 'pending')->exists();
    }

    public function totalVarianceValue(): string
    {
        return Decimal::normalize(
            (string) $this->variances()->sum('value'),
            Decimal::MONEY_SCALE,
        );
    }
}
