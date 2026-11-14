<?php

namespace App\Livewire\Procurement\Rfqs;

use App\Domain\Catalog\Models\Item;
use App\Domain\Catalog\Models\UnitOfMeasure;
use App\Domain\Organization\Models\BusinessUnit;
use App\Domain\Procurement\Models\PurchaseRequisition;
use App\Domain\Procurement\Models\RequestForQuotation;
use App\Domain\Procurement\Services\RequestForQuotationService;
use App\Domain\Supplier\Models\Supplier;
use App\DTOs\Procurement\RfqData;
use App\Livewire\Concerns\HandlesDomainActions;
use App\Support\Decimal;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Request for Quotation')]
class Form extends Component
{
    use HandlesDomainActions;

    public ?int $rfqId = null;

    /** @var array<string, mixed> */
    public array $form = [];

    /** @var list<array<string, mixed>> */
    public array $items = [];

    /** @var list<string> */
    public array $supplierIds = [];

    public function mount(?RequestForQuotation $rfq = null): void
    {
        if ($rfq !== null && $rfq->exists) {
            $this->authorize('update', $rfq);

            $this->rfqId = $rfq->id;
            $this->form = [
                'purchase_requisition_id' => $rfq->purchase_requisition_id !== null ? (string) $rfq->purchase_requisition_id : '',
                'business_unit_id' => (string) $rfq->business_unit_id,
                'title' => $rfq->title,
                'description' => (string) $rfq->description,
                'closing_date' => $rfq->closing_date->toDateString(),
            ];
            $this->items = $rfq->items->map(fn ($item) => [
                'purchase_requisition_item_id' => $item->purchase_requisition_item_id,
                'item_id' => (string) $item->item_id,
                'unit_of_measure_id' => (string) $item->unit_of_measure_id,
                'quantity' => (string) $item->quantity,
                'specification' => (string) $item->specification,
            ])->all();
            $this->supplierIds = $rfq->suppliers->pluck('supplier_id')->map(fn ($id) => (string) $id)->all();

            return;
        }

        $this->authorize('create', RequestForQuotation::class);

        $requisition = request()->integer('requisition');

        if ($requisition > 0) {
            $this->authorize('view', PurchaseRequisition::findOrFail($requisition));
        }

        $this->form = [
            'purchase_requisition_id' => $requisition > 0 ? (string) $requisition : '',
            'business_unit_id' => '',
            'title' => '',
            'description' => '',
            'closing_date' => now()->addDays(14)->toDateString(),
        ];
        $this->items = [$this->blankItem()];
    }

    public function updatedFormPurchaseRequisitionId(): void
    {
        $requisition = $this->form['purchase_requisition_id'] !== ''
            ? PurchaseRequisition::with('items')->find((int) $this->form['purchase_requisition_id'])
            : null;

        if ($requisition === null) {
            return;
        }

        $this->form['business_unit_id'] = (string) $requisition->business_unit_id;
        $this->form['title'] = 'Sourcing — '.$requisition->document_number;
        $this->form['description'] = $requisition->justification;

        $this->items = $requisition->items
            ->filter(fn ($item) => Decimal::isPositive($item->remainingQuantity()))
            ->map(fn ($item) => [
                'purchase_requisition_item_id' => $item->id,
                'item_id' => (string) $item->item_id,
                'unit_of_measure_id' => (string) $item->unit_of_measure_id,
                'quantity' => (string) $item->remainingQuantity(),
                'specification' => (string) $item->specification,
            ])->values()->all();
    }

    public function addItem(): void
    {
        $this->items[] = $this->blankItem();
    }

    public function removeItem(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);

        if ($this->items === []) {
            $this->items = [$this->blankItem()];
        }
    }

    public function save(RequestForQuotationService $service)
    {
        $rfq = $this->rfqId !== null ? RequestForQuotation::findOrFail($this->rfqId) : null;

        $rfq !== null
            ? $this->authorize('update', $rfq)
            : $this->authorize('create', RequestForQuotation::class);

        $this->validate([
            'form.business_unit_id' => ['required', 'integer', 'exists:business_units,id'],
            'form.title' => ['required', 'string', 'max:200'],
            'form.description' => ['nullable', 'string', 'max:2000'],
            'form.closing_date' => ['required', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'integer', 'exists:items,id'],
            'items.*.unit_of_measure_id' => ['required', 'integer', 'exists:units_of_measure,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.specification' => ['nullable', 'string', 'max:1000'],
            'supplierIds' => ['required', 'array', 'min:1'],
            'supplierIds.*' => ['integer', 'exists:suppliers,id'],
        ]);

        $data = RfqData::fromArray([
            ...$this->form,
            'purchase_requisition_id' => $this->form['purchase_requisition_id'] !== ''
                ? (int) $this->form['purchase_requisition_id']
                : null,
            'items' => $this->items,
            'supplier_ids' => $this->supplierIds,
        ]);

        $saved = $this->runDomainAction(
            fn () => $rfq !== null
                ? $service->update($rfq, $data, auth()->user())
                : $service->create($data, auth()->user()),
            $rfq !== null ? 'RFQ updated.' : 'RFQ created as a draft.',
        );

        if ($saved) {
            $savedRfq = $rfq ?? RequestForQuotation::query()->latest('id')->first();

            return redirect()->route('procurement.rfqs.show', $savedRfq);
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function blankItem(): array
    {
        return [
            'purchase_requisition_item_id' => null,
            'item_id' => '',
            'unit_of_measure_id' => '',
            'quantity' => '1',
            'specification' => '',
        ];
    }

    public function render()
    {
        return view('livewire.procurement.rfqs.form', [
            'businessUnits' => BusinessUnit::query()->where('is_active', true)->orderBy('name')->get(),
            'requisitions' => PurchaseRequisition::query()
                ->visibleTo(auth()->user())
                ->whereIn('status', ['approved', 'partially_approved'])
                ->latest('id')
                ->get(),
            'suppliers' => Supplier::query()->approved()->orderBy('name')->get(),
            'catalogItems' => Item::query()->where('is_active', true)->orderBy('name')->get(),
            'units' => UnitOfMeasure::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }
}
