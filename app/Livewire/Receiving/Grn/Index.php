<?php

namespace App\Livewire\Receiving\Grn;

use App\Domain\Receiving\Models\GoodsReceivedNote;
use App\Enums\GoodsReceivedNoteStatus;
use App\Livewire\Concerns\HandlesDomainActions;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Goods Received Notes')]
class Index extends Component
{
    use HandlesDomainActions, WithPagination;

    public string $search = '';

    public string $statusFilter = '';

    public function mount(): void
    {
        $this->authorize('viewAny', GoodsReceivedNote::class);
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
        $notes = GoodsReceivedNote::query()
            ->with(['purchaseOrder', 'supplier', 'warehouse', 'sra'])
            ->withCount('items')
            ->visibleTo(auth()->user())
            ->search($this->search)
            ->when($this->statusFilter !== '', fn (Builder $query) => $query->where('status', $this->statusFilter))
            ->latest('id')
            ->paginate(15);

        return view('livewire.receiving.grn.index', [
            'notes' => $notes,
            'statusOptions' => GoodsReceivedNoteStatus::cases(),
        ]);
    }
}
