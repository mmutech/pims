<?php

namespace App\Livewire\Procurement\Rfqs;

use App\Domain\Procurement\Models\RequestForQuotation;
use App\Domain\Procurement\Services\RequestForQuotationService;
use App\Enums\RequestForQuotationStatus;
use App\Livewire\Concerns\HandlesDomainActions;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Requests for Quotation')]
class Index extends Component
{
    use HandlesDomainActions, WithPagination;

    public string $search = '';

    public string $statusFilter = '';

    public function mount(): void
    {
        $this->authorize('viewAny', RequestForQuotation::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function delete(int $rfqId, RequestForQuotationService $service): void
    {
        $rfq = RequestForQuotation::findOrFail($rfqId);
        $this->authorize('delete', $rfq);

        if ($this->runDomainAction(function () use ($service, $rfq): void {
            $service->cancel($rfq, auth()->user());
            $rfq->delete();
        }, 'RFQ deleted.')) {
            $this->resetPage();
        }
    }

    public function render()
    {
        $rfqs = RequestForQuotation::query()
            ->with(['businessUnit', 'purchaseRequisition'])
            ->withCount(['items', 'suppliers', 'quotations'])
            ->visibleTo(auth()->user())
            ->search($this->search)
            ->when($this->statusFilter !== '', fn (Builder $query) => $query->where('status', $this->statusFilter))
            ->latest('id')
            ->paginate(15);

        return view('livewire.procurement.rfqs.index', [
            'rfqs' => $rfqs,
            'statusOptions' => RequestForQuotationStatus::cases(),
        ]);
    }
}
