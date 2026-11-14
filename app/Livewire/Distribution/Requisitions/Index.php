<?php

namespace App\Livewire\Distribution\Requisitions;

use App\Domain\Distribution\Models\MaterialRequisition;
use App\Domain\Distribution\Services\MaterialRequisitionService;
use App\Enums\RequisitionStatus;
use App\Livewire\Concerns\HandlesDomainActions;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Material Requisitions')]
class Index extends Component
{
    use HandlesDomainActions, WithPagination;

    public string $search = '';

    public string $statusFilter = '';

    public function mount(): void
    {
        $this->authorize('viewAny', MaterialRequisition::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function delete(int $requisitionId, MaterialRequisitionService $service): void
    {
        $requisition = MaterialRequisition::findOrFail($requisitionId);
        $this->authorize('delete', $requisition);

        if ($this->runDomainAction(fn () => $service->delete($requisition), 'Material requisition deleted.')) {
            $this->resetPage();
        }
    }

    public function render()
    {
        $requisitions = MaterialRequisition::query()
            ->with(['requestingDepartment', 'destinationWarehouse', 'requester', 'project'])
            ->withCount('items')
            ->visibleTo(auth()->user())
            ->search($this->search)
            ->when(
                $this->statusFilter !== '',
                fn (Builder $query) => $query->where('status', $this->statusFilter),
            )
            ->latest('id')
            ->paginate(15);

        return view('livewire.distribution.requisitions.index', [
            'requisitions' => $requisitions,
            'statusOptions' => RequisitionStatus::cases(),
        ]);
    }
}
