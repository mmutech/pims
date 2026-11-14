<?php

namespace App\Domain\Procurement\Services;

use App\Domain\Administration\Services\DocumentSequenceService;
use App\Domain\Procurement\Models\QuotationEvaluation;
use App\Domain\Procurement\Models\QuotationEvaluationScore;
use App\Domain\Procurement\Models\RequestForQuotation;
use App\Domain\Workflow\Contracts\ApprovalEngine;
use App\Domain\Workflow\Models\ApprovalRequest;
use App\Enums\DocumentType;
use App\Enums\QuotationEvaluationStatus;
use App\Enums\SupplierQuotationStatus;
use App\Models\User;
use App\Support\Decimal;
use DomainException;
use Illuminate\Support\Facades\DB;

class QuotationEvaluationService
{
    public function __construct(
        private readonly DocumentSequenceService $sequences,
        private readonly ApprovalEngine $approvals,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $scores
     */
    public function create(
        RequestForQuotation $rfq,
        int $recommendedQuotationId,
        ?string $comments,
        array $scores,
        User $actor,
    ): QuotationEvaluation {
        $recommended = $rfq->quotations()->find($recommendedQuotationId);

        if ($recommended === null) {
            throw new DomainException('The recommended quotation must belong to this RFQ.');
        }

        if ($recommended->status !== SupplierQuotationStatus::Submitted) {
            throw new DomainException('Only submitted quotations can be recommended.');
        }

        return DB::transaction(function () use ($rfq, $recommended, $comments, $scores, $actor): QuotationEvaluation {
            $evaluation = QuotationEvaluation::create([
                'document_number' => $this->sequences->nextNumber(DocumentType::QuotationEvaluation),
                'request_for_quotation_id' => $rfq->id,
                'status' => QuotationEvaluationStatus::Draft,
                'recommended_quotation_id' => $recommended->id,
                'comments' => $comments,
                'created_by' => $actor->id,
            ]);

            $this->syncScores($evaluation, $rfq, $scores);

            return $evaluation->refresh();
        });
    }

    /**
     * @param  list<array<string, mixed>>  $scores
     */
    public function syncScores(QuotationEvaluation $evaluation, RequestForQuotation $rfq, array $scores): void
    {
        if (! $evaluation->canBeEdited()) {
            throw new DomainException('Scores can only be edited while the evaluation is a draft.');
        }

        $validQuotationIds = $rfq->quotations()->pluck('id')->all();

        $evaluation->scores()->delete();

        foreach ($scores as $row) {
            $quotationId = (int) ($row['supplier_quotation_id'] ?? 0);

            if (! in_array($quotationId, $validQuotationIds, true)) {
                throw new DomainException('Evaluation scores must reference quotations from this RFQ.');
            }

            $weight = Decimal::normalize((string) ($row['weight'] ?? '0'), 2);
            $score = Decimal::normalize((string) ($row['score'] ?? '0'), 2);

            QuotationEvaluationScore::create([
                'quotation_evaluation_id' => $evaluation->id,
                'supplier_quotation_id' => $quotationId,
                'criterion' => (string) ($row['criterion'] ?? 'general'),
                'weight' => $weight,
                'score' => $score,
                'weighted_score' => Decimal::multiply(
                    $weight,
                    Decimal::divide($score, '100', Decimal::COST_SCALE),
                    Decimal::MONEY_SCALE,
                ),
            ]);
        }
    }

    public function submit(QuotationEvaluation $evaluation, User $actor): QuotationEvaluation
    {
        if (! $evaluation->canBeSubmitted()) {
            throw new DomainException('Select a recommended quotation before submitting the evaluation.');
        }

        return DB::transaction(function () use ($evaluation, $actor): QuotationEvaluation {
            $evaluation->forceFill(['status' => QuotationEvaluationStatus::PendingApproval])->save();

            $this->approvals->submit($evaluation, $actor);

            return $evaluation->refresh();
        });
    }

    public function cancel(QuotationEvaluation $evaluation, User $actor, string $reason): QuotationEvaluation
    {
        if ($evaluation->status === QuotationEvaluationStatus::Approved) {
            throw new DomainException('Approved evaluations cannot be cancelled.');
        }

        return DB::transaction(function () use ($evaluation, $actor, $reason): QuotationEvaluation {
            $request = ApprovalRequest::query()
                ->where('document_type', $evaluation->approvalDocumentType())
                ->where('document_id', $evaluation->id)
                ->where('status', 'pending')
                ->first();

            if ($request !== null) {
                $this->approvals->cancel($request, $actor, $reason);
            }

            $evaluation->forceFill(['status' => QuotationEvaluationStatus::Cancelled])->save();

            return $evaluation->refresh();
        });
    }

    public function delete(QuotationEvaluation $evaluation): void
    {
        if (! $evaluation->canBeEdited()) {
            throw new DomainException('Only draft evaluations can be deleted.');
        }

        $evaluation->delete();
    }
}
