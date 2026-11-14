<?php

namespace App\Livewire\Inventory\Balances;

use App\Domain\Inventory\Models\InventoryBalance;
use App\Domain\Warehouse\Models\Warehouse;
use App\Livewire\Concerns\HandlesDomainActions;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Inventory Balances')]
class Index extends Component
{
    use HandlesDomainActions, WithPagination;

    public string $search = '';

    public string $warehouseFilter = '';

    public bool $inStockOnly = true;

    public function mount(): void
    {
        $this->authorize('viewAny', InventoryBalance::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedWarehouseFilter(): void
    {
        $this->resetPage();
    }

    public function updatedInStockOnly(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $user = auth()->user();

        $balances = InventoryBalance::query()
            ->with(['warehouse', 'dimension', 'item', 'batch'])
            ->when(! $user->hasGlobalAccess(), fn (Builder $query) => $query->whereIn('warehouse_id', $user->activeWarehouseIds()))
            ->when($this->warehouseFilter !== '', fn (Builder $query) => $query->where('warehouse_id', (int) $this->warehouseFilter))
            ->when($this->inStockOnly, fn (Builder $query) => $query->where('on_hand_quantity', '>', 0))
            ->when($this->search !== '', fn (Builder $query) => $query->whereHas(
                'item',
                fn (Builder $query) => $query->where('code', 'like', "%{$this->search}%")
                    ->orWhere('name', 'like', "%{$this->search}%"),
            ))
            ->orderBy('warehouse_id')
            ->orderBy('item_id')
            ->paginate(20);

        $warehouseQuery = Warehouse::query()->where('is_active', true)->orderBy('name');

        if (! $user->hasGlobalAccess()) {
            $warehouseQuery->whereIn('id', $user->activeWarehouseIds());
        }

        return view('livewire.inventory.balances.index', [
            'balances' => $balances,
            'warehouses' => $warehouseQuery->get(),
        ]);
    }
}
