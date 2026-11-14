<?php

namespace App\Domain\Procurement\Models;

use App\Domain\Workflow\Contracts\Approvable;
use App\Domain\Workflow\Models\ApprovalAction;
use App\Domain\Workflow\Models\ApprovalRequest;
use App\Enums\ApprovalActionType;
use App\Enums\ApprovalStatus;
use App\Enums\QuotationEvaluationStatus;
use App\Enums\RequestForQuotationStatus;
use App\Enums\SupplierQuotationStatus;
use App\Models\User;
use App\Support\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class QuotationEvaluation extends Model implements Approvable
{
    use HasFactory, RecordsActivity;

    protected $fillable = [
        'document_number',
        'request_for_quotation_id',
        'status',
        'evaluated_by',
        'evaluated_at',
        'recommended_quotation_id',
        'comments',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => QuotationEvaluationStatus::class,
            'evaluated_at' => 'datetime',
        ];
    }

    public function requestForQuotation(): BelongsTo
    {
        return $this->belongsTo(RequestForQuotation::class);
    }

    public function recommendedQuotation(): BelongsTo
    {
        return $this->belongsTo(SupplierQuotation::class, 'recommended_quotation_id');
    }

    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluated_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scores(): HasMany
    {
        return $this->hasMany(QuotationEvaluationScore::class);
    }

    public function canBeEdited(): bool
    {
        return in_array($this->status, [
            QuotationEvaluationStatus::Draft,
            QuotationEvaluationStatus::Returned,
        ], true);
    }

    public function canBeSubmitted(): bool
    {
        return $this->canBeEdited() && $this->recommended_quotation_id !== null;
    }

    public function approvalDocumentType(): string
    {
        return 'quotation_evaluation';
    }

    /**
     * @return array<string, mixed>
     */
    public function approvalAttributes(): array
    {
        $rfq = $this->requestForQuotation;

        return [
            'amount' => (string) ($this->recommendedQuotation?->total_amount ?? '0'),
            'document_number' => $this->document_number,
            'requested_by' => $this->created_by,
            'created_by' => $this->created_by,
            'business_unit_id' => $rfq?->business_unit_id,
            'supplier_id' => $this->recommended_quotation_id !== null
                ? $this->recommendedQuotation?->supplier_id
                : null,
            'priority' => 'normal',
        ];
    }

    public function onApprovalStatusChanged(ApprovalRequest $request): void
    {
        $status = $request->status;

        if ($status === ApprovalStatus::Approved) {
            DB::transaction(function () use ($request): void {
                $recommended = $this->recommended_quotation_id;

                $this->forceFill([
                    'status' => QuotationEvaluationStatus::Approved,
                    'evaluated_at' => now(),
                    'evaluated_by' => $this->latestApproverId($request) ?? $request->requested_by,
                ])->save();

                if ($recommended !== null) {
                    SupplierQuotation::query()
                        ->where('request_for_quotation_id', $this->request_for_quotation_id)
                        ->whereKeyNot($recommended)
                        ->update(['status' => SupplierQuotationStatus::Rejected->value]);

                    SupplierQuotation::query()
                        ->whereKey($recommended)
                        ->update(['status' => SupplierQuotationStatus::Accepted->value]);
                }

                $this->requestForQuotation()->update([
                    'status' => RequestForQuotationStatus::Awarded->value,
                    'awarded_at' => now(),
                ]);
            });

            return;
        }

        $map = [
            ApprovalStatus::Rejected->value => QuotationEvaluationStatus::Rejected,
            ApprovalStatus::Returned->value => QuotationEvaluationStatus::Returned,
            ApprovalStatus::Cancelled->value => QuotationEvaluationStatus::Cancelled,
            ApprovalStatus::Expired->value => QuotationEvaluationStatus::Returned,
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
