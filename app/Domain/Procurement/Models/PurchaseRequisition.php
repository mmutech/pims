<?php

namespace App\Domain\Procurement\Models;

use App\Domain\Organization\Models\BusinessUnit;
use App\Domain\Organization\Models\CostCenter;
use App\Domain\Organization\Models\Department;
use App\Domain\Organization\Models\Project;
use App\Domain\Supplier\Models\Supplier;
use App\Domain\Workflow\Contracts\Approvable;
use App\Domain\Workflow\Models\ApprovalAction;
use App\Domain\Workflow\Models\ApprovalRequest;
use App\Enums\ApprovalActionType;
use App\Enums\ApprovalStatus;
use App\Enums\PurchaseRequisitionStatus;
use App\Models\User;
use App\Support\Concerns\RecordsActivity;
use App\Support\Decimal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseRequisition extends Model implements Approvable
{
    use HasFactory, RecordsActivity, SoftDeletes;

    protected $fillable = [
        'document_number',
        'requesting_department_id',
        'business_unit_id',
        'cost_center_id',
        'project_id',
        'requested_by',
        'suggested_supplier_id',
        'required_by',
        'justification',
        'status',
        'total_estimated_amount',
        'approved_amount',
        'currency',
        'version',
        'approved_at',
        'approved_by',
        'converted_at',
        'cancelled_at',
        'cancellation_reason',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => PurchaseRequisitionStatus::class,
            'required_by' => 'date',
            'total_estimated_amount' => 'decimal:2',
            'approved_amount' => 'decimal:2',
            'version' => 'integer',
            'approved_at' => 'datetime',
            'converted_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function requestingDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'requesting_department_id');
    }

    public function businessUnit(): BelongsTo
    {
        return $this->belongsTo(BusinessUnit::class);
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

    public function suggestedSupplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'suggested_supplier_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseRequisitionItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function requestForQuotations(): HasMany
    {
        return $this->hasMany(RequestForQuotation::class);
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
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

        return $query->where('requested_by', $user->id);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($term): void {
            $query->where('document_number', 'like', "%{$term}%")
                ->orWhere('justification', 'like', "%{$term}%")
                ->orWhereHas('requestingDepartment', fn (Builder $query) => $query->where('name', 'like', "%{$term}%"));
        });
    }

    public function canBeEdited(): bool
    {
        return in_array($this->status, [
            PurchaseRequisitionStatus::Draft,
            PurchaseRequisitionStatus::Returned,
        ], true);
    }

    public function canBeSubmitted(): bool
    {
        return $this->canBeEdited();
    }

    public function canBeCancelled(): bool
    {
        return ! in_array($this->status, [
            PurchaseRequisitionStatus::Cancelled,
            PurchaseRequisitionStatus::Converted,
        ], true);
    }

    public function isApprovedForSourcing(): bool
    {
        return in_array($this->status, [
            PurchaseRequisitionStatus::Approved,
            PurchaseRequisitionStatus::PartiallyApproved,
        ], true);
    }

    public function approvalDocumentType(): string
    {
        return 'purchase_requisition';
    }

    /**
     * @return array<string, mixed>
     */
    public function approvalAttributes(): array
    {
        return [
            'amount' => (string) $this->total_estimated_amount,
            'document_number' => $this->document_number,
            'requested_by' => $this->requested_by,
            'created_by' => $this->created_by ?? $this->requested_by,
            'updated_by' => $this->updated_by,
            'business_unit_id' => $this->business_unit_id,
            'department_id' => $this->requesting_department_id,
            'project_id' => $this->project_id,
            'currency' => $this->currency,
            'supplier_id' => $this->suggested_supplier_id,
            'priority' => 'normal',
        ];
    }

    public function onApprovalStatusChanged(ApprovalRequest $request): void
    {
        $status = $request->status;

        if ($status === ApprovalStatus::Approved) {
            $partial = $this->items()
                ->whereColumn('approved_quantity', '<', 'requested_quantity')
                ->exists();

            $this->forceFill([
                'status' => $partial ? PurchaseRequisitionStatus::PartiallyApproved : PurchaseRequisitionStatus::Approved,
                'approved_at' => now(),
                'approved_by' => $this->latestApproverId($request) ?? $request->requested_by,
                'approved_amount' => $this->approvedAmount(),
            ])->save();

            return;
        }

        $map = [
            ApprovalStatus::Rejected->value => PurchaseRequisitionStatus::Rejected,
            ApprovalStatus::Returned->value => PurchaseRequisitionStatus::Returned,
            ApprovalStatus::Cancelled->value => PurchaseRequisitionStatus::Cancelled,
            ApprovalStatus::Expired->value => PurchaseRequisitionStatus::Returned,
        ];

        $next = $map[$status->value] ?? null;

        if ($next !== null && $this->status !== PurchaseRequisitionStatus::Converted) {
            $attributes = ['status' => $next];

            if ($next === PurchaseRequisitionStatus::Cancelled) {
                $attributes['cancelled_at'] = now();
            }

            $this->forceFill($attributes)->save();
        }
    }

    /**
     * Sum of approved quantities at estimated prices, computed with fixed precision.
     */
    public function approvedAmount(): string
    {
        $total = '0';

        foreach ($this->items()->get(['approved_quantity', 'estimated_unit_price']) as $item) {
            $line = Decimal::multiply($item->approved_quantity, $item->estimated_unit_price, Decimal::MONEY_SCALE);
            $total = Decimal::add($total, $line, Decimal::MONEY_SCALE);
        }

        return $total;
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
