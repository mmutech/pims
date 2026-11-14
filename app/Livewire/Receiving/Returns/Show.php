<?php

namespace App\Livewire\Receiving\Returns;

use App\Domain\Receiving\Models\SupplierReturn;
use App\Domain\Receiving\Services\SupplierReturnService;
use App\Livewire\Concerns\HandlesDomainActions;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Supplier Return')]
class Show extends Component
{
    use HandlesDomainActions;

    public SupplierReturn $supplierReturn;

    public function mount(SupplierReturn $supplierReturn): void
    {
        $this->authorize('view', $supplierReturn);

        $this->supplierReturn = $supplierReturn;
        $this->loadReturn();
    }

    public function post(SupplierReturnService $service): void
    {
        $this->authorize('post', $this->supplierReturn);

        $this->runDomainAction(
            fn () => $service->post($this->supplierReturn, auth()->user()),
            'Supplier return posted out of stock.',
        );

        $this->loadReturn();
    }

    public function cancel(SupplierReturnService $service): void
    {
        $this->authorize('cancel', $this->supplierReturn);

        $this->runDomainAction(
            fn () => $service->cancel($this->supplierReturn, auth()->user()),
            'Supplier return cancelled.',
        );

        $this->loadReturn();
    }

    public function render()
    {
        return view('livewire.receiving.returns.show');
    }

    private function loadReturn(): void
    {
        $this->supplierReturn = $this->supplierReturn->fresh([
            'supplier',
            'warehouse',
            'grn',
            'creator',
            'poster',
            'items.item',
            'items.unitOfMeasure',
            'items.batch',
        ]);
    }
}
