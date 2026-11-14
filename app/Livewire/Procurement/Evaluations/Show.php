<?php

namespace App\Livewire\Procurement\Evaluations;

use App\Domain\Procurement\Models\QuotationEvaluation;
use App\Domain\Procurement\Services\QuotationEvaluationService;
use App\Domain\Workflow\Models\ApprovalRequest;
use App\Livewire\Concerns\HandlesDomainActions;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Quotation Evaluation')]
class Show extends Component
{
    use HandlesDomainActions;

    public QuotationEvaluation $evaluation;

    public bool $showCancelModal = false;

    public string $cancellationReason = '';

    public function mount(QuotationEvaluation $evaluation): void
    {
        $this->evaluation = $evaluation->load([
            'requestForQuotation',
            'recommendedQuotation.supplier',
            'recommendedQuotation.items.item',
            'scores.quotation.supplier',
            'evaluator',
        ]);

        $this->authorize('view', $this->evaluation);
    }

    public function submit(QuotationEvaluationService $service): void
    {
        $this->authorize('submit', $this->evaluation);

        if ($this->runDomainAction(
            fn () => $service->submit($this->evaluation, auth()->user()),
            'Evaluation submitted for approval.',
        )) {
            $this->evaluation->refresh();
        }
    }

    public function cancel(QuotationEvaluationService $service): void
    {
        $this->authorize('cancel', $this->evaluation);

        $this->validate(['cancellationReason' => ['required', 'string', 'max:500']]);

        if ($this->runDomainAction(
            fn () => $service->cancel($this->evaluation, auth()->user(), $this->cancellationReason),
            'Evaluation cancelled.',
        )) {
            $this->showCancelModal = false;
            $this->cancellationReason = '';
            $this->evaluation->refresh();
        }
    }

    public function render()
    {
        $approval = ApprovalRequest::query()
            ->where('document_type', $this->evaluation->approvalDocumentType())
            ->where('document_id', $this->evaluation->id)
            ->latest('id')
            ->first();

        return view('livewire.procurement.evaluations.show', [
            'approval' => $approval,
        ]);
    }
}
