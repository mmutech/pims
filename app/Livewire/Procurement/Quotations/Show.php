<?php

namespace App\Livewire\Procurement\Quotations;

use App\Domain\Procurement\Models\SupplierQuotation;
use App\Domain\Procurement\Services\SupplierQuotationService;
use App\Livewire\Concerns\HandlesDomainActions;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Supplier Quotation')]
class Show extends Component
{
    use HandlesDomainActions;

    public SupplierQuotation $quotation;

    public function mount(SupplierQuotation $quotation): void
    {
        $this->quotation = $quotation->load([
            'supplier',
            'requestForQuotation',
            'items.item',
            'items.unitOfMeasure',
            'items.rfqItem',
        ]);

        $this->authorize('view', $this->quotation);
    }

    public function submit(SupplierQuotationService $service): void
    {
        $this->authorize('submit', $this->quotation);

        if ($this->runDomainAction(
            fn () => $service->submit($this->quotation, auth()->user()),
            'Quotation submitted.',
        )) {
            $this->quotation->refresh();
        }
    }

    public function withdraw(SupplierQuotationService $service): void
    {
        $this->authorize('withdraw', $this->quotation);

        if ($this->runDomainAction(
            fn () => $service->withdraw($this->quotation, auth()->user()),
            'Quotation withdrawn.',
        )) {
            $this->quotation->refresh();
        }
    }

    public function delete(SupplierQuotationService $service)
    {
        $this->authorize('delete', $this->quotation);

        if ($this->runDomainAction(fn () => $service->delete($this->quotation), 'Quotation deleted.')) {
            return redirect()->route('procurement.quotations.index');
        }

        return null;
    }

    public function render()
    {
        return view('livewire.procurement.quotations.show');
    }
}
