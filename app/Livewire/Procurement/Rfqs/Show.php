<?php

namespace App\Livewire\Procurement\Rfqs;

use App\Domain\Procurement\Models\QuotationEvaluation;
use App\Domain\Procurement\Models\RequestForQuotation;
use App\Domain\Procurement\Services\QuotationEvaluationService;
use App\Domain\Procurement\Services\RequestForQuotationService;
use App\Enums\SupplierQuotationStatus;
use App\Livewire\Concerns\HandlesDomainActions;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Request for Quotation')]
class Show extends Component
{
    use HandlesDomainActions;

    public RequestForQuotation $rfq;

    public bool $showCancelModal = false;

    public bool $showEvaluationModal = false;

    /** @var array<string, mixed> */
    public array $evaluationForm = [];

    public function mount(RequestForQuotation $rfq): void
    {
        $this->rfq = $rfq->load([
            'items.item',
            'items.unitOfMeasure',
            'suppliers.supplier',
            'quotations.supplier',
            'purchaseRequisition',
            'businessUnit',
        ]);

        $this->authorize('view', $this->rfq);

        $this->evaluationForm = [
            'recommended_quotation_id' => '',
            'comments' => '',
            'submit_now' => true,
        ];
    }

    public function issue(RequestForQuotationService $service): void
    {
        $this->authorize('issue', $this->rfq);

        if ($this->runDomainAction(
            fn () => $service->issue($this->rfq, auth()->user()),
            'RFQ issued to invited suppliers.',
        )) {
            $this->rfq->refresh();
        }
    }

    public function close(RequestForQuotationService $service): void
    {
        $this->authorize('update', $this->rfq);

        if ($this->runDomainAction(
            fn () => $service->close($this->rfq, auth()->user()),
            'RFQ closed for new quotations.',
        )) {
            $this->rfq->refresh();
        }
    }

    public function cancel(RequestForQuotationService $service): void
    {
        $this->authorize('cancel', $this->rfq);

        if ($this->runDomainAction(
            fn () => $service->cancel($this->rfq, auth()->user()),
            'RFQ cancelled.',
        )) {
            $this->showCancelModal = false;
            $this->rfq->refresh();
        }
    }

    public function createEvaluation(QuotationEvaluationService $service)
    {
        $this->authorize('create', QuotationEvaluation::class);

        $this->validate([
            'evaluationForm.recommended_quotation_id' => ['required', 'integer', 'exists:supplier_quotations,id'],
            'evaluationForm.comments' => ['nullable', 'string', 'max:5000'],
        ]);

        try {
            $evaluation = $service->create(
                $this->rfq,
                (int) $this->evaluationForm['recommended_quotation_id'],
                $this->evaluationForm['comments'] !== '' ? $this->evaluationForm['comments'] : null,
                [],
                auth()->user(),
            );

            if ((bool) $this->evaluationForm['submit_now']) {
                $service->submit($evaluation, auth()->user());
            }

            return redirect()->route('procurement.evaluations.show', $evaluation);
        } catch (\DomainException $exception) {
            $this->error($exception->getMessage());

            return null;
        }
    }

    public function render()
    {
        return view('livewire.procurement.rfqs.show', [
            'submittedQuotations' => $this->rfq->quotations()
                ->where('status', SupplierQuotationStatus::Submitted->value)
                ->with('supplier')
                ->orderBy('total_amount')
                ->get(),
            'evaluations' => $this->rfq->evaluations()->with('recommendedQuotation.supplier')->latest('id')->get(),
            'canCreateQuotation' => $this->rfq->status->canReceiveQuotations(),
        ]);
    }
}
