<?php

namespace App\Livewire\Inventory\Transactions;

use App\Domain\Inventory\Models\StockTransaction;
use App\Enums\StockMovementType;
use App\Livewire\Concerns\HandlesDomainActions;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Stock Transactions')]
class Index extends Component
{
    use HandlesDomainActions, WithPagination;

    public string $search = '';

    public string $typeFilter = '';

    public function mount(): void
    {
        $this->authorize('viewAny', StockTransaction::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedTypeFilter(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $user = auth()->user();

        $transactions = StockTransaction::query()
            ->with(['warehouse', 'postedBy', 'source'])
            ->withCount('items')
            ->when(! $user->hasGlobalAccess(), fn (Builder $query) => $query->whereIn('warehouse_id', $user->activeWarehouseIds()))
            ->when($this->typeFilter !== '', fn (Builder $query) => $query->where('transaction_type', $this->typeFilter))
            ->when($this->search !== '', fn (Builder $query) => $query->where(
                'transaction_number',
                'like',
                "%{$this->search}%",
            ))
            ->latest('id')
            ->paginate(20);

        return view('livewire.inventory.transactions.index', [
            'transactions' => $transactions,
            'typeOptions' => StockMovementType::cases(),
        ]);
    }
}
