<?php

namespace App\Livewire\Procurement\Orders;

use App\Domain\Procurement\Models\PurchaseOrder;
use App\Domain\Procurement\Services\PurchaseOrderService;
use App\Enums\PurchaseOrderStatus;
use App\Livewire\Concerns\HandlesDomainActions;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Purchase Orders')]
class Index extends Component
{
    use HandlesDomainActions, WithPagination;

    public string $search = '';

    public string $statusFilter = '';

    public function mount(): void
    {
        $this->authorize('viewAny', PurchaseOrder::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function delete(int $orderId, PurchaseOrderService $service): void
    {
        $order = PurchaseOrder::findOrFail($orderId);
        $this->authorize('delete', $order);

        if ($this->runDomainAction(fn () => $service->delete($order), 'Purchase order deleted.')) {
            $this->resetPage();
        }
    }

    public function render()
    {
        $orders = PurchaseOrder::query()
            ->with(['supplier', 'deliveryWarehouse'])
            ->withCount('items')
            ->visibleTo(auth()->user())
            ->search($this->search)
            ->when($this->statusFilter !== '', fn (Builder $query) => $query->where('status', $this->statusFilter))
            ->latest('id')
            ->paginate(15);

        return view('livewire.procurement.orders.index', [
            'orders' => $orders,
            'statusOptions' => PurchaseOrderStatus::cases(),
        ]);
    }
}
