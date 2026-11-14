<?php

namespace App\Livewire\Distribution\Returns;

use App\Domain\Distribution\Models\MaterialReturn;
use App\Domain\Distribution\Services\MaterialReturnService;
use App\Enums\ReturnStatus;
use App\Livewire\Concerns\HandlesDomainActions;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Material Returns')]
class Index extends Component
{
    use HandlesDomainActions, WithPagination;

    public string $search = '';

    public string $statusFilter = '';

    public function mount(): void
    {
        $this->authorize('viewAny', MaterialReturn::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function delete(int $returnId, MaterialReturnService $service): void
    {
        $return = MaterialReturn::findOrFail($returnId);
        $this->authorize('delete', $return);

        if ($this->runDomainAction(fn () => $service->delete($return), 'Material return deleted.')) {
            $this->resetPage();
        }
    }

    public function render()
    {
        $returns = MaterialReturn::query()
            ->with(['originalIssue', 'warehouse', 'returnedBy'])
            ->withCount('items')
            ->visibleTo(auth()->user())
            ->search($this->search)
            ->when(
                $this->statusFilter !== '',
                fn (Builder $query) => $query->where('status', $this->statusFilter),
            )
            ->latest('id')
            ->paginate(15);

        return view('livewire.distribution.returns.index', [
            'returns' => $returns,
            'statusOptions' => ReturnStatus::cases(),
        ]);
    }
}
