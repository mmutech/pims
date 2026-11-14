<?php

namespace App\Domain\Distribution\Models;

use App\Domain\Warehouse\Models\Warehouse;
use App\Domain\Workflow\Contracts\Approvable;
use App\Domain\Workflow\Models\ApprovalAction;
use App\Domain\Workflow\Models\ApprovalRequest;
use App\Enums\ApprovalActionType;
use App\Enums\ApprovalStatus;
use App\Enums\TransferStatus;
use App\Models\User;
use App\Support\Concerns\RecordsActivity;
use App\Support\Decimal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockTransfer extends Model implements Approvable
{
    use HasFactory, RecordsActivity, SoftDeletes;

    protected $fillable = [
        'document_number',
        'source_warehouse_id',
        'destination_warehouse_id',
        'requested_by',
        'approved_by',
        'reason',
        'status',
        'required_by',
        'approved_at',
        'completed_at',
        'cancelled_at',
        'cancellation_reason',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => TransferStatus::class,
            'required_by' => 'date',
            'approved_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function sourceWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'source_warehouse_id');
    }

    public function destinationWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'destination_warehouse_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockTransferItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function dispatches(): HasMany
    {
        return $this->hasMany(StockTransferDispatch::class);
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(StockTransferReceipt::class);
    }

    public function discrepancies(): HasMany
    {
        return $this->hasMany(StockTransferDiscrepancy::class);
    }

    public function approvalRequests(): HasMany
    {
        return $this->hasMany(ApprovalRequest::class, 'document_id')
            ->where('document_type', $this->approvalDocumentType());
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasGlobalAccess()) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($user): void {
            $warehouseIds = $user->activeWarehouseIds();

            if ($warehouseIds !== []) {
                $query->whereIn('source_warehouse_id', $warehouseIds)
                    ->orWhereIn('destination_warehouse_id', $warehouseIds);
            }

            $query->orWhere('requested_by', $user->id);
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
        return in_array($this->status, [TransferStatus::Draft, TransferStatus::PendingApproval], true);
    }

    public function canBeSubmitted(): bool
    {
        return $this->status === TransferStatus::Draft;
    }

    public function canBeDispatched(): bool
    {
        return in_array($this->status, [
            TransferStatus::Approved,
            TransferStatus::PartiallyDispatched,
            TransferStatus::InTransit,
            TransferStatus::PartiallyReceived,
        ], true);
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, [
            TransferStatus::Draft,
            TransferStatus::PendingApproval,
            TransferStatus::Approved,
        ], true) && ! $this->dispatches()->exists();
    }

    public function isCompleted(): bool
    {
        return $this->status === TransferStatus::Completed;
    }

    public function hasOpenDiscrepancies(): bool
    {
        return $this->discrepancies()->where('resolution', 'pending')->exists();
    }

    public function approvalDocumentType(): string
    {
        return 'stock_transfer';
    }

    /**
     * @return array<string, mixed>
     */
    public function approvalAttributes(): array
    {
        return [
            'amount' => '0',
            'document_number' => $this->document_number,
            'requested_by' => $this->requested_by,
            'created_by' => $this->created_by ?? $this->requested_by,
            'updated_by' => $this->updated_by,
            'warehouse_id' => $this->source_warehouse_id,
            'destination_warehouse_id' => $this->destination_warehouse_id,
            'priority' => 'normal',
        ];
    }

    public function onApprovalStatusChanged(ApprovalRequest $request): void
    {
        if ($request->status === ApprovalStatus::Approved) {
            foreach ($this->items()->get() as $item) {
                if (Decimal::compare($item->approved_quantity, '0') <= 0) {
                    $item->forceFill(['approved_quantity' => $item->requested_quantity])->save();
                }
            }

            $this->forceFill([
                'status' => TransferStatus::Approved,
                'approved_at' => now(),
                'approved_by' => $this->latestApproverId($request) ?? $request->requested_by,
            ])->save();

            return;
        }

        $map = [
            ApprovalStatus::Rejected->value => TransferStatus::Draft,
            ApprovalStatus::Returned->value => TransferStatus::Draft,
            ApprovalStatus::Cancelled->value => TransferStatus::Cancelled,
            ApprovalStatus::Expired->value => TransferStatus::Draft,
        ];

        $next = $map[$request->status->value] ?? null;

        if ($next !== null && $this->status !== TransferStatus::Completed) {
            $attributes = ['status' => $next];

            if ($next === TransferStatus::Cancelled) {
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
