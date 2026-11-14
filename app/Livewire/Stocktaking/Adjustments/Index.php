<?php

namespace App\Livewire\Stocktaking\Adjustments;

use App\Domain\Stocktaking\Models\StockAdjustment;
use App\Domain\Stocktaking\Services\StockAdjustmentService;
use App\Enums\AdjustmentStatus;
use App\Enums\AdjustmentType;
use App\Livewire\Concerns\HandlesDomainActions;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Stock Adjustments')]
class Index extends Component
{
    use HandlesDomainActions, WithPagination;

    public string $search = '';

    public string $statusFilter = '';

    public string $typeFilter = '';

    public function mount(): void
    {
        $this->authorize('viewAny', StockAdjustment::class);
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

    public function delete(int $adjustmentId, StockAdjustmentService $service): void
    {
        $adjustment = StockAdjustment::query()->findOrFail($adjustmentId);

        $this->authorize('delete', $adjustment);

        if ($this->runDomainAction(fn () => $service->delete($adjustment), 'Stock adjustment deleted.')) {
            $this->resetPage();
        }
    }

    public function render()
    {
        $adjustments = StockAdjustment::query()
            ->with(['warehouse', 'creator'])
            ->withCount('items')
            ->visibleTo(auth()->user())
            ->search($this->search)
            ->when(
                $this->statusFilter !== '',
                fn (Builder $query) => $query->where('status', $this->statusFilter),
            )
            ->when(
                $this->typeFilter !== '',
                fn (Builder $query) => $query->where('adjustment_type', $this->typeFilter),
            )
            ->latest('id')
            ->paginate(15);

        return view('livewire.stocktaking.adjustments.index', [
            'adjustments' => $adjustments,
            'statusOptions' => AdjustmentStatus::cases(),
            'typeOptions' => AdjustmentType::cases(),
        ]);
    }
}
