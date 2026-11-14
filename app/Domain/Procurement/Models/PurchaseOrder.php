<?php

namespace App\Domain\Procurement\Models;

use App\Domain\Supplier\Models\Supplier;
use App\Domain\Warehouse\Models\Warehouse;
use App\Domain\Workflow\Contracts\Approvable;
use App\Domain\Workflow\Models\ApprovalAction;
use App\Domain\Workflow\Models\ApprovalRequest;
use App\Enums\ApprovalActionType;
use App\Enums\ApprovalStatus;
use App\Enums\PurchaseOrderStatus;
use App\Models\User;
use App\Support\Concerns\RecordsActivity;
use App\Support\Decimal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrder extends Model implements Approvable
{
    use HasFactory, RecordsActivity, SoftDeletes;

    protected $fillable = [
        'document_number',
        'supplier_id',
        'delivery_warehouse_id',
        'purchase_requisition_id',
        'supplier_quotation_id',
        'currency',
        'payment_terms',
        'incoterms',
        'expected_delivery_date',
        'subtotal',
        'tax_amount',
        'total_amount',
        'status',
        'version',
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
            'status' => PurchaseOrderStatus::class,
            'expected_delivery_date' => 'date',
            'subtotal' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'version' => 'integer',
            'approved_at' => 'datetime',
            'closed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function deliveryWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'delivery_warehouse_id');
    }

    public function purchaseRequisition(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequisition::class);
    }

    public function supplierQuotation(): BelongsTo
    {
        return $this->belongsTo(SupplierQuotation::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function amendments(): HasMany
    {
        return $this->hasMany(PurchaseOrderAmendment::class)->orderBy('amendment_number');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasGlobalAccess()) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($user): void {
            $warehouseIds = $user->activeWarehouseIds();

            if ($warehouseIds !== []) {
                $query->whereIn('purchase_orders.delivery_warehouse_id', $warehouseIds);
            }

            $query->orWhere('purchase_orders.created_by', $user->id);
        });
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($term): void {
            $query->where('document_number', 'like', "%{$term}%")
                ->orWhereHas('supplier', fn (Builder $query) => $query->where('name', 'like', "%{$term}%"));
        });
    }

    public function canBeEdited(): bool
    {
        return $this->status === PurchaseOrderStatus::Draft;
    }

    public function canBeSubmitted(): bool
    {
        return $this->status === PurchaseOrderStatus::Draft;
    }

    public function canBeAmended(): bool
    {
        return in_array($this->status, [
            PurchaseOrderStatus::Approved,
            PurchaseOrderStatus::PartiallyReceived,
        ], true);
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, [
            PurchaseOrderStatus::Draft,
            PurchaseOrderStatus::PendingApproval,
            PurchaseOrderStatus::Approved,
            PurchaseOrderStatus::PartiallyReceived,
        ], true);
    }

    public function canBeClosed(): bool
    {
        return in_array($this->status, [
            PurchaseOrderStatus::Approved,
            PurchaseOrderStatus::PartiallyReceived,
            PurchaseOrderStatus::FullyReceived,
        ], true);
    }

    public function approvalDocumentType(): string
    {
        return 'purchase_order';
    }

    /**
     * @return array<string, mixed>
     */
    public function approvalAttributes(): array
    {
        return [
            'amount' => (string) $this->total_amount,
            'document_number' => $this->document_number,
            'requested_by' => $this->created_by,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'warehouse_id' => $this->delivery_warehouse_id,
            'supplier_id' => $this->supplier_id,
            'currency' => $this->currency,
            'priority' => 'normal',
        ];
    }

    public function onApprovalStatusChanged(ApprovalRequest $request): void
    {
        $status = $request->status;

        if ($status === ApprovalStatus::Approved) {
            $this->forceFill([
                'status' => PurchaseOrderStatus::Approved,
                'approved_at' => now(),
                'approved_by' => $this->latestApproverId($request) ?? $request->requested_by,
            ])->save();

            return;
        }

        if ($status === ApprovalStatus::Cancelled) {
            $this->forceFill([
                'status' => PurchaseOrderStatus::Cancelled,
                'cancelled_at' => now(),
            ])->save();

            return;
        }

        // Rejected, returned, and expired orders fall back to draft so they can be corrected.
        if (in_array($status, [
            ApprovalStatus::Rejected,
            ApprovalStatus::Returned,
            ApprovalStatus::Expired,
        ], true)) {
            $this->forceFill(['status' => PurchaseOrderStatus::Draft])->save();
        }
    }

    /**
     * Recalculate subtotal, tax, and total from order lines using fixed precision.
     */
    public function recalculateTotals(): void
    {
        $subtotal = '0';
        $tax = '0';

        foreach ($this->items()->get() as $item) {
            $subtotal = Decimal::add($subtotal, $item->line_total, Decimal::MONEY_SCALE);

            $lineTax = Decimal::multiply(
                Decimal::subtract($item->line_total, $item->discount_amount, Decimal::MONEY_SCALE),
                Decimal::divide($item->tax_rate, '100', Decimal::COST_SCALE),
                Decimal::MONEY_SCALE,
            );

            $tax = Decimal::add($tax, $lineTax, Decimal::MONEY_SCALE);
        }

        $this->forceFill([
            'subtotal' => $subtotal,
            'tax_amount' => $tax,
            'total_amount' => Decimal::add($subtotal, $tax, Decimal::MONEY_SCALE),
        ])->save();
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
