<?php

namespace App\Livewire\Procurement\Requisitions;

use App\Domain\Procurement\Models\PurchaseRequisition;
use App\Domain\Procurement\Models\RequestForQuotation;
use App\Domain\Procurement\Services\PurchaseRequisitionService;
use App\Domain\Procurement\Services\RequestForQuotationService;
use App\Domain\Supplier\Models\Supplier;
use App\Domain\Workflow\Models\ApprovalRequest;
use App\DTOs\Procurement\RfqData;
use App\Livewire\Concerns\HandlesDomainActions;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Purchase Requisition')]
class Show extends Component
{
    use HandlesDomainActions;

    public PurchaseRequisition $requisition;

    /** @var array<int, string> */
    public array $approvedQuantities = [];

    public bool $showCancelModal = false;

    public string $cancellationReason = '';

    public bool $showRfqModal = false;

    /** @var array<string, mixed> */
    public array $rfqForm = [];

    public function mount(PurchaseRequisition $requisition): void
    {
        $this->requisition = $requisition->load([
            'items.item',
            'items.unitOfMeasure',
            'requestingDepartment',
            'businessUnit',
            'costCenter',
            'project',
            'requester',
            'suggestedSupplier',
        ]);

        $this->authorize('view', $this->requisition);

        foreach ($this->requisition->items as $item) {
            $this->approvedQuantities[$item->id] = (string) $item->approved_quantity;
        }

        $this->rfqForm = [
            'title' => 'Sourcing — '.$this->requisition->document_number,
            'description' => $this->requisition->justification,
            'closing_date' => now()->addDays(14)->toDateString(),
            'supplier_ids' => [],
        ];
    }

    public function submit(PurchaseRequisitionService $service): void
    {
        $this->authorize('submit', $this->requisition);

        if ($this->runDomainAction(
            fn () => $service->submit($this->requisition, auth()->user()),
            'Requisition submitted for approval.',
        )) {
            $this->requisition->refresh();
        }
    }

    public function saveApprovedQuantities(PurchaseRequisitionService $service): void
    {
        $this->authorize('adjustApprovedQuantities', $this->requisition);

        if ($this->runDomainAction(
            fn () => $service->updateApprovedQuantities($this->requisition, $this->approvedQuantities, auth()->user()),
            'Approved quantities updated.',
        )) {
            $this->requisition->refresh();
            $this->requisition->load('items.item', 'items.unitOfMeasure');
        }
    }

    public function cancel(PurchaseRequisitionService $service): void
    {
        $this->authorize('cancel', $this->requisition);

        $this->validate(['cancellationReason' => ['required', 'string', 'max:500']]);

        if ($this->runDomainAction(
            fn () => $service->cancel($this->requisition, auth()->user(), $this->cancellationReason),
            'Requisition cancelled.',
        )) {
            $this->showCancelModal = false;
            $this->cancellationReason = '';
            $this->requisition->refresh();
        }
    }

    public function createRfq(RequestForQuotationService $service)
    {
        $this->authorize('create', RequestForQuotation::class);

        $this->validate([
            'rfqForm.title' => ['required', 'string', 'max:200'],
            'rfqForm.description' => ['nullable', 'string', 'max:2000'],
            'rfqForm.closing_date' => ['required', 'date', 'after:today'],
            'rfqForm.supplier_ids' => ['required', 'array', 'min:1'],
            'rfqForm.supplier_ids.*' => ['integer', 'exists:suppliers,id'],
        ]);

        try {
            $rfq = $service->createFromRequisition(
                $this->requisition,
                RfqData::fromArray([
                    ...$this->rfqForm,
                    'business_unit_id' => $this->requisition->business_unit_id,
                    'purchase_requisition_id' => $this->requisition->id,
                ]),
                auth()->user(),
            );

            return redirect()->route('procurement.rfqs.show', $rfq);
        } catch (\DomainException $exception) {
            $this->error($exception->getMessage());

            return null;
        }
    }

    public function render()
    {
        $approval = ApprovalRequest::query()
            ->where('document_type', $this->requisition->approvalDocumentType())
            ->where('document_id', $this->requisition->id)
            ->latest('id')
            ->first();

        return view('livewire.procurement.requisitions.show', [
            'approval' => $approval,
            'rfqs' => $this->requisition->requestForQuotations()->latest('id')->get(),
            'orders' => $this->requisition->purchaseOrders()->latest('id')->get(),
            'suppliers' => Supplier::query()->approved()->orderBy('name')->get(),
        ]);
    }
}
