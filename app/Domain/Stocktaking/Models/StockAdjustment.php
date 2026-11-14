<?php

namespace App\Domain\Stocktaking\Models;

use App\Domain\Inventory\Models\StockTransaction;
use App\Domain\Warehouse\Models\Warehouse;
use App\Domain\Workflow\Contracts\Approvable;
use App\Domain\Workflow\Models\ApprovalAction;
use App\Domain\Workflow\Models\ApprovalRequest;
use App\Enums\AdjustmentReason;
use App\Enums\AdjustmentStatus;
use App\Enums\AdjustmentType;
use App\Enums\ApprovalActionType;
use App\Enums\ApprovalStatus;
use App\Models\User;
use App\Support\Decimal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockAdjustment extends Model implements Approvable
{
    use SoftDeletes;

    protected $fillable = [
        'document_number',
        'warehouse_id',
        'stock_count_id',
        'adjustment_type',
        'reason_code',
        'reason',
        'status',
        'total_value',
        'approval_request_id',
        'approved_at',
        'approved_by',
        'posted_at',
        'posted_by',
        'reversal_of_id',
        'stock_transaction_id',
        'cancelled_at',
        'cancellation_reason',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'adjustment_type' => AdjustmentType::class,
            'reason_code' => AdjustmentReason::class,
            'status' => AdjustmentStatus::class,
            'total_value' => 'decimal:2',
            'approved_at' => 'datetime',
            'posted_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function stockCount(): BelongsTo
    {
        return $this->belongsTo(StockCount::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockAdjustmentItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(StockTransaction::class, 'stock_transaction_id');
    }

    public function approvalRequest(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }

    public function reversals(): HasMany
    {
        return $this->hasMany(self::class, 'reversal_of_id');
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($term): void {
            $query->where('document_number', 'like', "%{$term}%")
                ->orWhere('reason', 'like', "%{$term}%")
                ->orWhere('reason_code', 'like', "%{$term}%");
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

    public function canBeEdited(): bool
    {
        return $this->status->canBeEdited();
    }

    public function canBeSubmitted(): bool
    {
        return $this->status->canBeSubmitted();
    }

    public function canBePosted(): bool
    {
        return $this->status->canBePosted();
    }

    public function canBeReversed(): bool
    {
        return $this->status->canBeReversed();
    }

    public function canBeCancelled(): bool
    {
        return $this->status->canBeCancelled();
    }

    public function requiresApproval(): bool
    {
        return $this->totalValueExceedsThreshold();
    }

    public function totalValueExceedsThreshold(): bool
    {
        $threshold = (string) config('inventory.adjustment_approval_threshold', '0');

        if (! Decimal::isPositive($threshold, Decimal::MONEY_SCALE)) {
            return false;
        }

        return Decimal::compare($this->total_value, $threshold, Decimal::MONEY_SCALE) >= 0;
    }

    public function isCreatedBy(User $user): bool
    {
        return (int) $this->created_by === (int) $user->id;
    }

    public function approvalDocumentType(): string
    {
        return 'stock_adjustment';
    }

    /**
     * @return array<string, mixed>
     */
    public function approvalAttributes(): array
    {
        return [
            'amount' => Decimal::normalize($this->total_value, Decimal::MONEY_SCALE),
            'document_number' => $this->document_number,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'warehouse_id' => $this->warehouse_id,
            'reason_code' => $this->reason_code?->value,
            'adjustment_type' => $this->adjustment_type?->value,
            'stock_count_id' => $this->stock_count_id,
            'priority' => 'normal',
        ];
    }

    public function onApprovalStatusChanged(ApprovalRequest $request): void
    {
        if ($request->status === ApprovalStatus::Approved) {
            $this->forceFill([
                'status' => AdjustmentStatus::Approved,
                'approved_at' => now(),
                'approved_by' => $this->latestApproverId($request) ?? $request->requested_by,
            ])->save();

            return;
        }

        $map = [
            ApprovalStatus::Rejected->value => AdjustmentStatus::Draft,
            ApprovalStatus::Returned->value => AdjustmentStatus::Draft,
            ApprovalStatus::Cancelled->value => AdjustmentStatus::Cancelled,
            ApprovalStatus::Expired->value => AdjustmentStatus::Draft,
        ];

        $next = $map[$request->status->value] ?? null;

        if ($next !== null && ! $this->status->isFinal()) {
            $attributes = ['status' => $next];

            if ($next === AdjustmentStatus::Cancelled) {
                $attributes['cancelled_at'] = now();
            }

            $this->forceFill($attributes)->save();
        }
    }

    private function latestApproverId(ApprovalRequest $request): ?int
    {
        $actorId = ApprovalAction::query()
            ->where('approval_request_id', $request->id)
            ->where('action', ApprovalActionType::Approve->value)
            ->latest('id')
            ->value('actor_id');

        return $actorId !== null ? (int) $actorId : null;
    }
}
