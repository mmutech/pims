<?php

namespace App\Domain\Distribution\Models;

use App\Domain\Organization\Models\CostCenter;
use App\Domain\Organization\Models\Department;
use App\Domain\Organization\Models\Project;
use App\Domain\Warehouse\Models\Warehouse;
use App\Domain\Workflow\Contracts\Approvable;
use App\Domain\Workflow\Models\ApprovalAction;
use App\Domain\Workflow\Models\ApprovalRequest;
use App\Enums\ApprovalActionType;
use App\Enums\ApprovalStatus;
use App\Enums\RequisitionStatus;
use App\Models\User;
use App\Support\Concerns\RecordsActivity;
use App\Support\Decimal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MaterialRequisition extends Model implements Approvable
{
    use HasFactory, RecordsActivity, SoftDeletes;

    protected $fillable = [
        'document_number',
        'requesting_department_id',
        'cost_center_id',
        'project_id',
        'requested_by',
        'destination_warehouse_id',
        'purpose',
        'required_by',
        'priority',
        'status',
        'approved_at',
        'approved_by',
        'closed_at',
        'cancelled_at',
        'cancellation_reason',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => RequisitionStatus::class,
            'required_by' => 'date',
            'approved_at' => 'datetime',
            'closed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function requestingDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'requesting_department_id');
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function destinationWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'destination_warehouse_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(MaterialRequisitionItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function issues(): HasMany
    {
        return $this->hasMany(StockIssue::class);
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
                $query->whereIn('destination_warehouse_id', $warehouseIds);
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
                ->orWhere('purpose', 'like', "%{$term}%")
                ->orWhereHas('requestingDepartment', fn (Builder $query) => $query->where('name', 'like', "%{$term}%"));
        });
    }

    public function canBeEdited(): bool
    {
        return in_array($this->status, [RequisitionStatus::Draft, RequisitionStatus::Returned], true);
    }

    public function canBeSubmitted(): bool
    {
        return $this->canBeEdited();
    }

    public function canBeCancelled(): bool
    {
        return ! in_array($this->status, [
            RequisitionStatus::Cancelled,
            RequisitionStatus::Closed,
        ], true);
    }

    public function isApprovedForIssue(): bool
    {
        return in_array($this->status, [
            RequisitionStatus::Approved,
            RequisitionStatus::PartiallyApproved,
            RequisitionStatus::Reserved,
            RequisitionStatus::PartiallyIssued,
            RequisitionStatus::Issued,
            RequisitionStatus::Acknowledged,
        ], true);
    }

    public function hasOutstandingQuantity(): bool
    {
        return $this->items()
            ->where('outstanding_quantity', '>', 0)
            ->exists();
    }

    public function approvalDocumentType(): string
    {
        return 'material_requisition';
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
            'department_id' => $this->requesting_department_id,
            'project_id' => $this->project_id,
            'warehouse_id' => $this->destination_warehouse_id,
            'priority' => $this->priority,
        ];
    }

    public function onApprovalStatusChanged(ApprovalRequest $request): void
    {
        $status = $request->status;

        if ($status === ApprovalStatus::Approved) {
            $partial = $this->items()
                ->whereColumn('approved_quantity', '<', 'requested_quantity')
                ->exists();

            foreach ($this->items()->get() as $item) {
                if (Decimal::compare($item->approved_quantity, '0') <= 0) {
                    $item->forceFill(['approved_quantity' => $item->requested_quantity])->save();
                }

                $item->forceFill([
                    'outstanding_quantity' => $item->approved_quantity,
                ])->save();
            }

            $this->forceFill([
                'status' => $partial ? RequisitionStatus::PartiallyApproved : RequisitionStatus::Approved,
                'approved_at' => now(),
                'approved_by' => $this->latestApproverId($request) ?? $request->requested_by,
            ])->save();

            return;
        }

        $map = [
            ApprovalStatus::Rejected->value => RequisitionStatus::Rejected,
            ApprovalStatus::Returned->value => RequisitionStatus::Returned,
            ApprovalStatus::Cancelled->value => RequisitionStatus::Cancelled,
            ApprovalStatus::Expired->value => RequisitionStatus::Returned,
        ];

        $next = $map[$status->value] ?? null;

        if ($next !== null && $this->status !== RequisitionStatus::Closed) {
            $attributes = ['status' => $next];

            if ($next === RequisitionStatus::Cancelled) {
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
