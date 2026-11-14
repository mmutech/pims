<?php

use App\Domain\Organization\Models\BusinessUnit;
use App\Domain\Procurement\Models\QuotationEvaluation;
use App\Domain\Procurement\Models\SupplierQuotation;
use App\Domain\Procurement\Services\PurchaseRequisitionService;
use App\Domain\Procurement\Services\QuotationEvaluationService;
use App\Domain\Procurement\Services\SupplierQuotationService;
use App\Domain\Supplier\Models\Supplier;
use App\DTOs\Procurement\QuotationData;
use App\Enums\QuotationEvaluationStatus;
use App\Enums\RequestForQuotationStatus;
use App\Enums\SupplierQuotationStatus;
use App\Models\User;

beforeEach(function () {
    $this->seedProcurement();
    $this->makeProcurementFlow('purchase_requisition');
    $this->makeProcurementFlow('quotation_evaluation');

    $this->businessUnit = BusinessUnit::factory()->create();
    $this->department = $this->makeDepartment();
    $this->requester = User::factory()
        ->withRole('Department Requester')
        ->create();
    $this->officer = User::factory()
        ->withRole('Procurement Officer')
        ->create();
    [$this->item, $this->unit] = $this->makeCatalogItem();
    $this->manager = $this->procurementApprover();
    $this->supplierA = Supplier::factory()->approved()->create();
    $this->supplierB = Supplier::factory()->approved()->create();

    $requisition = $this->makeRequisition(
        $this->requester,
        $this->businessUnit,
        $this->department,
        $this->item,
        $this->unit,
    );

    app(PurchaseRequisitionService::class)->submit($requisition, $this->requester);
    $this->approveDocument($requisition, $this->manager);

    $this->rfq = $this->makeIssuedRfq(
        $requisition->refresh(),
        $this->officer,
        [$this->supplierA, $this->supplierB],
    );

    $this->quotationA = $this->makeQuotation($this->rfq, $this->supplierA, $this->officer, '1250.00');
    $this->quotationB = $this->makeQuotation($this->rfq, $this->supplierB, $this->officer, '1350.00');
});

it('awards the recommended quotation and rejects the rest when approved', function () {
    $evaluation = $this->makeEvaluation($this->rfq, $this->quotationA, $this->officer);

    expect($evaluation->status)->toBe(QuotationEvaluationStatus::Draft)
        ->and($evaluation->recommended_quotation_id)->toBe($this->quotationA->id);

    app(QuotationEvaluationService::class)->submit($evaluation, $this->officer);

    expect($evaluation->fresh()->status)->toBe(QuotationEvaluationStatus::PendingApproval);

    $this->approveDocument($evaluation, $this->manager);

    $evaluation->refresh();
    $this->rfq->refresh();

    expect($evaluation->status)->toBe(QuotationEvaluationStatus::Approved)
        ->and($evaluation->evaluated_by)->toBe($this->manager->id)
        ->and($evaluation->evaluated_at)->not->toBeNull()
        ->and($this->quotationA->fresh()->status)->toBe(SupplierQuotationStatus::Accepted)
        ->and($this->quotationB->fresh()->status)->toBe(SupplierQuotationStatus::Rejected)
        ->and($this->rfq->status)->toBe(RequestForQuotationStatus::Awarded)
        ->and($this->rfq->awarded_at)->not->toBeNull();
});

it('requires the recommended quotation to belong to the RFQ', function () {
    $otherQuotation = SupplierQuotation::factory()->submitted()->create();

    expect(fn () => app(QuotationEvaluationService::class)->create(
        $this->rfq,
        $otherQuotation->id,
        'Invalid recommendation',
        [],
        $this->officer,
    ))->toThrow(DomainException::class, 'must belong to this RFQ');
});

it('only allows submitted quotations to be recommended', function () {
    // Replace supplier B's submitted quotation with a draft so the RFQ has one draft to recommend.
    $this->quotationB->items()->delete();
    $this->quotationB->forceDelete();

    $draftQuotation = app(SupplierQuotationService::class)->create(QuotationData::fromArray([
        'request_for_quotation_id' => $this->rfq->id,
        'supplier_id' => $this->supplierB->id,
        'items' => [[
            'rfq_item_id' => $this->rfq->items()->first()->id,
            'quantity' => '10',
            'unit_price' => '1400.00',
        ]],
    ]), $this->officer);

    expect(fn () => app(QuotationEvaluationService::class)->create(
        $this->rfq,
        $draftQuotation->id,
        'Draft recommendation',
        [],
        $this->officer,
    ))->toThrow(DomainException::class, 'Only submitted quotations can be recommended');
});

it('computes weighted evaluation scores with fixed precision', function () {
    $evaluation = $this->makeEvaluation($this->rfq, $this->quotationA, $this->officer);

    app(QuotationEvaluationService::class)->syncScores($evaluation, $this->rfq, [
        [
            'supplier_quotation_id' => $this->quotationA->id,
            'criterion' => 'price',
            'weight' => '30',
            'score' => '80',
        ],
        [
            'supplier_quotation_id' => $this->quotationB->id,
            'criterion' => 'price',
            'weight' => '30',
            'score' => '70',
        ],
    ]);

    $scores = $evaluation->scores()->orderBy('id')->get();

    expect($scores)->toHaveCount(2)
        ->and($scores[0]->weighted_score)->toBe('24.00')
        ->and($scores[1]->weighted_score)->toBe('21.00');
});

it('cancels a draft evaluation without awarding the RFQ', function () {
    $evaluation = $this->makeEvaluation($this->rfq, $this->quotationA, $this->officer);

    $cancelled = app(QuotationEvaluationService::class)->cancel(
        $evaluation,
        $this->officer,
        'Award deferred to next quarter.',
    );

    expect($cancelled->status)->toBe(QuotationEvaluationStatus::Cancelled)
        ->and($this->quotationA->fresh()->status)->toBe(SupplierQuotationStatus::Submitted)
        ->and($this->rfq->fresh()->status)->toBe(RequestForQuotationStatus::Issued);
});

it('does not allow an approved evaluation to be cancelled', function () {
    $evaluation = $this->makeEvaluation($this->rfq, $this->quotationA, $this->officer);

    app(QuotationEvaluationService::class)->submit($evaluation, $this->officer);
    $this->approveDocument($evaluation, $this->manager);

    expect(fn () => app(QuotationEvaluationService::class)->cancel(
        $evaluation->refresh(),
        $this->officer,
        'Too late',
    ))->toThrow(DomainException::class, 'Approved evaluations cannot be cancelled');
});

it('scopes evaluations to the creator', function () {
    $evaluation = $this->makeEvaluation($this->rfq, $this->quotationA, $this->officer);

    $outsider = User::factory()
        ->withRole('Procurement Officer')
        ->create();

    expect($this->officer->can('view', $evaluation))->toBeTrue()
        ->and($outsider->can('view', $evaluation))->toBeFalse()
        ->and($outsider->can('submit', $evaluation))->toBeFalse()
        ->and(QuotationEvaluation::query()->count())->toBe(1);
});
