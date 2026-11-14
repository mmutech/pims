<?php

namespace App\Livewire\Receiving\Sra;

use App\Domain\Receiving\Models\StoreReceiptAdvice;
use App\Domain\Receiving\Services\StoreReceiptAdviceService;
use App\Enums\ReceiptStatus;
use App\Livewire\Concerns\HandlesDomainActions;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Store Receipt Advices')]
class Index extends Component
{
    use HandlesDomainActions, WithPagination;

    public string $search = '';

    public string $statusFilter = '';

    public function mount(): void
    {
        $this->authorize('viewAny', StoreReceiptAdvice::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function delete(int $sraId, StoreReceiptAdviceService $service): void
    {
        $sra = StoreReceiptAdvice::findOrFail($sraId);
        $this->authorize('delete', $sra);

        if ($this->runDomainAction(fn () => $service->delete($sra), 'Receipt advice deleted.')) {
            $this->resetPage();
        }
    }

    public function render()
    {
        $advices = StoreReceiptAdvice::query()
            ->with(['purchaseOrder', 'supplier', 'warehouse'])
            ->withCount('items')
            ->visibleTo(auth()->user())
            ->search($this->search)
            ->when($this->statusFilter !== '', fn (Builder $query) => $query->where('status', $this->statusFilter))
            ->latest('id')
            ->paginate(15);

        return view('livewire.receiving.sra.index', [
            'advices' => $advices,
            'statusOptions' => ReceiptStatus::cases(),
        ]);
    }
}
