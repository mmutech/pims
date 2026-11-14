<?php

namespace App\Livewire\Stocktaking\Reconciliations;

use App\Domain\Stocktaking\Models\StockReconciliation;
use App\Enums\ReconciliationStatus;
use App\Livewire\Concerns\HandlesDomainActions;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Stock Reconciliations')]
class Index extends Component
{
    use HandlesDomainActions, WithPagination;

    public string $search = '';

    public string $statusFilter = '';

    public function mount(): void
    {
        $this->authorize('viewAny', StockReconciliation::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $reconciliations = StockReconciliation::query()
            ->with(['warehouse', 'preparedBy', 'approvedBy', 'stockCount'])
            ->visibleTo(auth()->user())
            ->when(
                $this->search !== '',
                fn (Builder $query) => $query->where(function (Builder $query): void {
                    $query->where('document_number', 'like', '%'.$this->search.'%')
                        ->orWhereHas(
                            'stockCount',
                            fn (Builder $query) => $query->where('document_number', 'like', '%'.$this->search.'%'),
                        );
                }),
            )
            ->when(
                $this->statusFilter !== '',
                fn (Builder $query) => $query->where('status', $this->statusFilter),
            )
            ->latest('id')
            ->paginate(15);

        return view('livewire.stocktaking.reconciliations.index', [
            'reconciliations' => $reconciliations,
            'statusOptions' => ReconciliationStatus::cases(),
        ]);
    }
}
