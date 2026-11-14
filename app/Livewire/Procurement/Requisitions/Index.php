<?php

namespace App\Livewire\Procurement\Requisitions;

use App\Domain\Procurement\Models\PurchaseRequisition;
use App\Domain\Procurement\Services\PurchaseRequisitionService;
use App\Enums\PurchaseRequisitionStatus;
use App\Livewire\Concerns\HandlesDomainActions;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Purchase Requisitions')]
class Index extends Component
{
    use HandlesDomainActions, WithPagination;

    public string $search = '';

    public string $statusFilter = '';

    public function mount(): void
    {
        $this->authorize('viewAny', PurchaseRequisition::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function delete(int $requisitionId, PurchaseRequisitionService $service): void
    {
        $requisition = PurchaseRequisition::findOrFail($requisitionId);
        $this->authorize('delete', $requisition);

        if ($this->runDomainAction(fn () => $service->delete($requisition), 'Requisition deleted.')) {
            $this->resetPage();
        }
    }

    public function render()
    {
        $requisitions = PurchaseRequisition::query()
            ->with(['requestingDepartment', 'businessUnit', 'requester'])
            ->withCount(['items', 'requestForQuotations'])
            ->visibleTo(auth()->user())
            ->search($this->search)
            ->when(
                $this->statusFilter !== '',
                fn (Builder $query) => $query->where('status', $this->statusFilter),
            )
            ->latest('id')
            ->paginate(15);

        return view('livewire.procurement.requisitions.index', [
            'requisitions' => $requisitions,
            'statusOptions' => PurchaseRequisitionStatus::cases(),
        ]);
    }
}
