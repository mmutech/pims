<?php

namespace App\Livewire\Distribution\Transfers;

use App\Domain\Distribution\Models\StockTransfer;
use App\Domain\Distribution\Services\StockTransferService;
use App\Enums\TransferStatus;
use App\Livewire\Concerns\HandlesDomainActions;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Stock Transfers')]
class Index extends Component
{
    use HandlesDomainActions, WithPagination;

    public string $search = '';

    public string $statusFilter = '';

    public function mount(): void
    {
        $this->authorize('viewAny', StockTransfer::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function delete(int $transferId, StockTransferService $service): void
    {
        $transfer = StockTransfer::findOrFail($transferId);
        $this->authorize('delete', $transfer);

        if ($this->runDomainAction(fn () => $service->delete($transfer), 'Draft transfer deleted.')) {
            $this->resetPage();
        }
    }

    public function render()
    {
        $transfers = StockTransfer::query()
            ->with(['sourceWarehouse', 'destinationWarehouse', 'requester'])
            ->withCount('items')
            ->visibleTo(auth()->user())
            ->search($this->search)
            ->when(
                $this->statusFilter !== '',
                fn (Builder $query) => $query->where('status', $this->statusFilter),
            )
            ->latest('id')
            ->paginate(15);

        return view('livewire.distribution.transfers.index', [
            'transfers' => $transfers,
            'statusOptions' => TransferStatus::cases(),
        ]);
    }
}
