<?php

namespace App\Domain\Procurement\Models;

use App\Domain\Procurement\Services\PurchaseOrderAmendmentService;
use App\Domain\Workflow\Contracts\Approvable;
use App\Domain\Workflow\Models\ApprovalAction;
use App\Domain\Workflow\Models\ApprovalRequest;
use App\Enums\ApprovalActionType;
use App\Enums\ApprovalStatus;
use App\Enums\PurchaseOrderAmendmentStatus;
use App\Models\User;
use App\Support\Concerns\RecordsActivity;
use App\Support\Decimal;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class PurchaseOrderAmendment extends Model implements Approvable
{
    use HasFactory, RecordsActivity;

    protected $fillable = [
        'purchase_order_id',
        'amendment_number',
        'reason',
        'status',
        'requested_by',
        'approved_by',
        'approved_at',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => PurchaseOrderAmendmentStatus::class,
            'amendment_number' => 'integer',
            'approved_at' => 'datetime',
            'submitted_at' => 'datetime',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderAmendmentItem::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function canBeEdited(): bool
    {
        return in_array($this->status, [
            PurchaseOrderAmendmentStatus::Draft,
            PurchaseOrderAmendmentStatus::Returned,
        ], true);
    }

    public function canBeSubmitted(): bool
    {
        return $this->canBeEdited() && $this->items()->exists();
    }

    public function approvalDocumentType(): string
    {
        return 'purchase_order_amendment';
    }

    /**
     * Project the purchase order totals this amendment would produce if approved.
     *
     * @return array{subtotal: string, tax: string, total: string}
     */
    public function projectedTotals(): array
    {
        $order = $this->purchaseOrder()->first();

        if ($order === null) {
            return ['subtotal' => '0.00', 'tax' => '0.00', 'total' => '0.00'];
        }

        /** @var Collection<int, PurchaseOrderAmendmentItem> $changes */
        $changes = $this->items()->get()->keyBy('purchase_order_item_id');

        $subtotal = '0';
        $tax = '0';

        foreach ($order->items()->get() as $item) {
            /** @var PurchaseOrderAmendmentItem|null $change */
            $change = $changes->get($item->id);

            $lineTotal = $change?->amended_line_total ?? $item->line_total;

            $subtotal = Decimal::add($subtotal, $lineTotal, Decimal::MONEY_SCALE);

            $lineTax = Decimal::multiply(
                Decimal::subtract($lineTotal, $item->discount_amount, Decimal::MONEY_SCALE),
                Decimal::divide($item->tax_rate, '100', Decimal::COST_SCALE),
                Decimal::MONEY_SCALE,
            );

            $tax = Decimal::add($tax, $lineTax, Decimal::MONEY_SCALE);
        }

        return [
            'subtotal' => $subtotal,
            'tax' => $tax,
            'total' => Decimal::add($subtotal, $tax, Decimal::MONEY_SCALE),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function approvalAttributes(): array
    {
        $order = $this->purchaseOrder;
        $totals = $this->projectedTotals();

        return [
            'amount' => $totals['total'],
            'document_number' => $order?->document_number,
            'requested_by' => $this->requested_by,
            'created_by' => $this->requested_by,
            'warehouse_id' => $order?->delivery_warehouse_id,
            'supplier_id' => $order?->supplier_id,
            'currency' => $order?->currency,
            'priority' => 'normal',
            'is_amendment' => true,
        ];
    }

    public function onApprovalStatusChanged(ApprovalRequest $request): void
    {
        $status = $request->status;

        if ($status === ApprovalStatus::Approved) {
            $this->forceFill([
                'status' => PurchaseOrderAmendmentStatus::Approved,
                'approved_at' => now(),
                'approved_by' => $this->latestApproverId($request) ?? $request->requested_by,
            ])->save();

            app(PurchaseOrderAmendmentService::class)->apply($this);

            return;
        }

        $map = [
            ApprovalStatus::Rejected->value => PurchaseOrderAmendmentStatus::Rejected,
            ApprovalStatus::Returned->value => PurchaseOrderAmendmentStatus::Returned,
            ApprovalStatus::Cancelled->value => PurchaseOrderAmendmentStatus::Cancelled,
            ApprovalStatus::Expired->value => PurchaseOrderAmendmentStatus::Returned,
        ];

        $next = $map[$status->value] ?? null;

        if ($next !== null) {
            $this->forceFill(['status' => $next])->save();
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
