<?php

namespace App\Livewire\Distribution\Requisitions;

use App\Domain\Distribution\Models\MaterialRequisition;
use App\Domain\Distribution\Services\MaterialRequisitionService;
use App\Domain\Workflow\Models\ApprovalRequest;
use App\Enums\RequisitionStatus;
use App\Livewire\Concerns\HandlesDomainActions;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Material Requisition')]
class Show extends Component
{
    use HandlesDomainActions;

    public MaterialRequisition $requisition;

    /** @var array<int, string> */
    public array $approvedQuantities = [];

    public bool $showCancelModal = false;

    public string $cancellationReason = '';

    public function mount(MaterialRequisition $requisition): void
    {
        $this->authorize('view', $requisition);

        $this->requisition = $requisition;
        $this->loadRequisition();
    }

    public function submit(MaterialRequisitionService $service): void
    {
        $this->authorize('submit', $this->requisition);

        if ($this->runDomainAction(
            fn () => $service->submit($this->requisition, auth()->user()),
            'Material requisition submitted for approval.',
        )) {
            $this->loadRequisition();
        }
    }

    public function saveApprovedQuantities(MaterialRequisitionService $service): void
    {
        $this->authorize('adjustApprovedQuantities', $this->requisition);

        if ($this->runDomainAction(
            fn () => $service->updateApprovedQuantities(
                $this->requisition,
                $this->approvedQuantities,
                auth()->user(),
            ),
            'Approved quantities updated.',
        )) {
            $this->loadRequisition();
        }
    }

    public function reserve(MaterialRequisitionService $service): void
    {
        $this->authorize('reserve', $this->requisition);

        if ($this->runDomainAction(
            fn () => $service->reserve($this->requisition, auth()->user()),
            'Stock reserved against the approved quantities.',
        )) {
            $this->loadRequisition();
        }
    }

    public function cancel(MaterialRequisitionService $service): void
    {
        $this->authorize('cancel', $this->requisition);

        $this->validate([
            'cancellationReason' => ['required', 'string', 'max:500'],
        ]);

        if ($this->runDomainAction(
            fn () => $service->cancel($this->requisition, auth()->user(), $this->cancellationReason),
            'Material requisition cancelled.',
        )) {
            $this->showCancelModal = false;
            $this->cancellationReason = '';
            $this->loadRequisition();
        }
    }

    public function render()
    {
        $approval = ApprovalRequest::query()
            ->where('document_type', $this->requisition->approvalDocumentType())
            ->where('document_id', $this->requisition->id)
            ->latest('id')
            ->first();

        return view('livewire.distribution.requisitions.show', [
            'approval' => $approval,
            'canReserve' => in_array($this->requisition->status, [
                RequisitionStatus::Approved,
                RequisitionStatus::PartiallyApproved,
            ], true),
        ]);
    }

    private function loadRequisition(): void
    {
        $this->requisition = $this->requisition->fresh([
            'items.item',
            'items.unitOfMeasure',
            'items.backorders',
            'requestingDepartment',
            'costCenter',
            'project',
            'requester',
            'approvedBy',
            'destinationWarehouse',
            'issues',
        ]);

        $this->approvedQuantities = [];

        foreach ($this->requisition->items as $item) {
            $this->approvedQuantities[$item->id] = (string) $item->approved_quantity;
        }
    }
}
