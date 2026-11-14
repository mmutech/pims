<?php

namespace App\Livewire\Procurement\Quotations;

use App\Domain\Procurement\Models\SupplierQuotation;
use App\Enums\SupplierQuotationStatus;
use App\Livewire\Concerns\HandlesDomainActions;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Supplier Quotations')]
class Index extends Component
{
    use HandlesDomainActions, WithPagination;

    public string $search = '';

    public string $statusFilter = '';

    public function mount(): void
    {
        $this->authorize('viewAny', SupplierQuotation::class);
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
        $quotations = SupplierQuotation::query()
            ->with(['supplier', 'requestForQuotation'])
            ->withCount('items')
            ->visibleTo(auth()->user())
            ->search($this->search)
            ->when($this->statusFilter !== '', fn (Builder $query) => $query->where('status', $this->statusFilter))
            ->latest('id')
            ->paginate(15);

        return view('livewire.procurement.quotations.index', [
            'quotations' => $quotations,
            'statusOptions' => SupplierQuotationStatus::cases(),
        ]);
    }
}
