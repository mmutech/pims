<?php

namespace App\Livewire\Receiving\Returns;

use App\Domain\Receiving\Models\SupplierReturn;
use App\Domain\Receiving\Services\SupplierReturnService;
use App\Enums\ReturnStatus;
use App\Livewire\Concerns\HandlesDomainActions;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Supplier Returns')]
class Index extends Component
{
    use HandlesDomainActions, WithPagination;

    public string $search = '';

    public string $statusFilter = '';

    public function mount(): void
    {
        $this->authorize('viewAny', SupplierReturn::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function delete(int $returnId, SupplierReturnService $service): void
    {
        $return = SupplierReturn::findOrFail($returnId);
        $this->authorize('delete', $return);

        if ($this->runDomainAction(fn () => $service->delete($return), 'Supplier return deleted.')) {
            $this->resetPage();
        }
    }

    public function render()
    {
        $returns = SupplierReturn::query()
            ->with(['supplier', 'warehouse', 'grn'])
            ->withCount('items')
            ->visibleTo(auth()->user())
            ->search($this->search)
            ->when($this->statusFilter !== '', fn (Builder $query) => $query->where('status', $this->statusFilter))
            ->latest('id')
            ->paginate(15);

        return view('livewire.receiving.returns.index', [
            'returns' => $returns,
            'statusOptions' => ReturnStatus::cases(),
        ]);
    }
}
