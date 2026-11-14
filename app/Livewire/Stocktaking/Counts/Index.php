<?php

namespace App\Livewire\Stocktaking\Counts;

use App\Domain\Stocktaking\Models\StockCount;
use App\Domain\Stocktaking\Services\StockCountService;
use App\Enums\CountStatus;
use App\Enums\CountType;
use App\Livewire\Concerns\HandlesDomainActions;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Stock Counts')]
class Index extends Component
{
    use HandlesDomainActions, WithPagination;

    public string $search = '';

    public string $statusFilter = '';

    public string $typeFilter = '';

    public function mount(): void
    {
        $this->authorize('viewAny', StockCount::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedTypeFilter(): void
    {
        $this->resetPage();
    }

    public function delete(int $countId, StockCountService $service): void
    {
        $count = StockCount::query()->findOrFail($countId);

        $this->authorize('delete', $count);

        if ($this->runDomainAction(fn () => $service->delete($count), 'Stock count deleted.')) {
            $this->resetPage();
        }
    }

    public function render()
    {
        $counts = StockCount::query()
            ->with(['warehouse', 'creator'])
            ->withCount(['items', 'variances', 'adjustments'])
            ->visibleTo(auth()->user())
            ->search($this->search)
            ->when(
                $this->statusFilter !== '',
                fn (Builder $query) => $query->where('status', $this->statusFilter),
            )
            ->when(
                $this->typeFilter !== '',
                fn (Builder $query) => $query->where('count_type', $this->typeFilter),
            )
            ->latest('id')
            ->paginate(15);

        return view('livewire.stocktaking.counts.index', [
            'counts' => $counts,
            'statusOptions' => CountStatus::cases(),
            'typeOptions' => CountType::cases(),
        ]);
    }
}
