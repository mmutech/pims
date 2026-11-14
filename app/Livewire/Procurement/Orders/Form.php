<?php

namespace App\Livewire\Procurement\Orders;

use App\Domain\Catalog\Models\Item;
use App\Domain\Catalog\Models\UnitOfMeasure;
use App\Domain\Procurement\Models\PurchaseOrder;
use App\Domain\Procurement\Models\PurchaseRequisition;
use App\Domain\Procurement\Models\SupplierQuotation;
use App\Domain\Procurement\Services\PurchaseOrderService;
use App\Domain\Supplier\Models\Supplier;
use App\Domain\Warehouse\Models\Warehouse;
use App\DTOs\Procurement\PurchaseOrderData;
use App\Enums\PurchaseOrderStatus;
use App\Enums\SupplierQuotationStatus;
use App\Livewire\Concerns\HandlesDomainActions;
use App\Support\Decimal;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Purchase Order')]
class Form extends Component
{
    use HandlesDomainActions;

    public ?int $orderId = null;

    /** @var array<string, mixed> */
    public array $form = [];

    /** @var list<array<string, mixed>> */
    public array $items = [];

    public function mount(?PurchaseOrder $order = null): void
    {
        if ($order !== null && $order->exists) {
            $this->authorize('update', $order);

            $this->orderId = $order->id;
            $this->form = [
                'supplier_id' => (string) $order->supplier_id,
                'delivery_warehouse_id' => (string) $order->delivery_warehouse_id,
                'purchase_requisition_id' => $order->purchase_requisition_id !== null ? (string) $order->purchase_requisition_id : '',
                'supplier_quotation_id' => $order->supplier_quotation_id !== null ? (string) $order->supplier_quotation_id : '',
                'currency' => $order->currency,
                'payment_terms' => (string) $order->payment_terms,
                'incoterms' => (string) $order->incoterms,
                'expected_delivery_date' => $order->expected_delivery_date?->toDateString() ?? '',
            ];

            $this->items = $order->items->map(fn ($item) => [
                'item_id' => (string) $item->item_id,
                'unit_of_measure_id' => (string) $item->unit_of_measure_id,
                'ordered_quantity' => (string) $item->ordered_quantity,
                'unit_price' => (string) $item->unit_price,
                'tax_rate' => (string) $item->tax_rate,
                'discount_amount' => (string) $item->discount_amount,
                'required_by' => $item->required_by?->toDateString() ?? '',
                'cost_center_id' => $item->cost_center_id !== null ? (string) $item->cost_center_id : '',
                'project_id' => $item->project_id !== null ? (string) $item->project_id : '',
                'purchase_requisition_item_id' => $item->purchase_requisition_item_id,
            ])->all();

            return;
        }

        $this->authorize('create', PurchaseOrder::class);

        $warehouses = $this->accessibleWarehouses();
        $requisition = request()->integer('requisition');
        $quotation = request()->integer('quotation');

        $this->form = [
            'supplier_id' => '',
            'delivery_warehouse_id' => (string) ($warehouses->first()->id ?? ''),
            'purchase_requisition_id' => '',
            'supplier_quotation_id' => '',
            'currency' => 'NGN',
            'payment_terms' => '',
            'incoterms' => '',
            'expected_delivery_date' => now()->addDays(21)->toDateString(),
        ];

        $this->items = [];

        if ($quotation > 0) {
            $this->hydrateFromQuotation(SupplierQuotation::with('items', 'requestForQuotation')->findOrFail($quotation));
        } elseif ($requisition > 0) {
            $this->hydrateFromRequisition(PurchaseRequisition::with('items')->findOrFail($requisition));
        }

        if ($this->items === []) {
            $this->items = [$this->blankItem()];
        }
    }

    public function updatedFormSupplierQuotationId(): void
    {
        if ($this->form['supplier_quotation_id'] === '') {
            return;
        }

        $quotation = SupplierQuotation::with('items', 'requestForQuotation')
            ->find((int) $this->form['supplier_quotation_id']);

        if ($quotation === null || $quotation->status !== SupplierQuotationStatus::Accepted) {
            return;
        }

        $this->hydrateFromQuotation($quotation);
    }

    public function updatedFormPurchaseRequisitionId(): void
    {
        if ($this->form['purchase_requisition_id'] === '') {
            return;
        }

        $requisition = PurchaseRequisition::with('items')
            ->find((int) $this->form['purchase_requisition_id']);

        if ($requisition === null || ! $requisition->isApprovedForSourcing()) {
            return;
        }

        $this->hydrateFromRequisition($requisition);
        $this->form['supplier_quotation_id'] = '';
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

    public function save(PurchaseOrderService $service)
    {
        $order = $this->orderId !== null ? PurchaseOrder::findOrFail($this->orderId) : null;

        $order !== null
            ? $this->authorize('update', $order)
            : $this->authorize('create', PurchaseOrder::class);

        $this->validate([
            'form.supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'form.delivery_warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'form.purchase_requisition_id' => ['nullable', 'integer', 'exists:purchase_requisitions,id'],
            'form.supplier_quotation_id' => ['nullable', 'integer', 'exists:supplier_quotations,id'],
            'form.currency' => ['required', 'string', 'size:3'],
            'form.payment_terms' => ['nullable', 'string', 'max:100'],
            'form.incoterms' => ['nullable', 'string', 'max:20'],
            'form.expected_delivery_date' => ['nullable', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'integer', 'exists:items,id'],
            'items.*.unit_of_measure_id' => ['required', 'integer', 'exists:units_of_measure,id'],
            'items.*.ordered_quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'items.*.discount_amount' => ['required', 'numeric', 'min:0'],
        ]);

        $data = PurchaseOrderData::fromArray([
            ...$this->form,
            'purchase_requisition_id' => $this->form['purchase_requisition_id'] !== ''
                ? (int) $this->form['purchase_requisition_id']
                : null,
            'supplier_quotation_id' => $this->form['supplier_quotation_id'] !== ''
                ? (int) $this->form['supplier_quotation_id']
                : null,
            'created_by' => auth()->id(),
            'items' => $this->items,
        ]);

        $saved = $this->runDomainAction(
            fn () => $order !== null
                ? $service->update($order, $data, auth()->user())
                : $service->create($data, auth()->user()),
            $order !== null ? 'Purchase order updated.' : 'Purchase order created as a draft.',
        );

        if ($saved) {
            $savedOrder = $order ?? PurchaseOrder::query()->latest('id')->first();

            return redirect()->route('procurement.orders.show', $savedOrder);
        }

        return null;
    }

    private function hydrateFromQuotation(SupplierQuotation $quotation): void
    {
        $this->form['supplier_id'] = (string) $quotation->supplier_id;
        $this->form['supplier_quotation_id'] = (string) $quotation->id;
        $this->form['purchase_requisition_id'] = $quotation->requestForQuotation?->purchase_requisition_id !== null
            ? (string) $quotation->requestForQuotation->purchase_requisition_id
            : '';
        $this->form['currency'] = $quotation->currency;
        $this->form['payment_terms'] = (string) $quotation->payment_terms;

        $this->items = $quotation->items->map(fn ($item) => [
            'item_id' => (string) $item->item_id,
            'unit_of_measure_id' => (string) $item->unit_of_measure_id,
            'ordered_quantity' => (string) $item->quantity,
            'unit_price' => (string) $item->unit_price,
            'tax_rate' => (string) $item->tax_rate,
            'discount_amount' => (string) $item->discount_amount,
            'required_by' => '',
            'cost_center_id' => '',
            'project_id' => '',
            'purchase_requisition_item_id' => null,
        ])->all();
    }

    private function hydrateFromRequisition(PurchaseRequisition $requisition): void
    {
        $this->form['purchase_requisition_id'] = (string) $requisition->id;
        $this->form['supplier_id'] = $requisition->suggested_supplier_id !== null
            ? (string) $requisition->suggested_supplier_id
            : '';

        $this->items = $requisition->items
            ->filter(fn ($item) => Decimal::isPositive($item->remainingQuantity()))
            ->map(fn ($item) => [
                'item_id' => (string) $item->item_id,
                'unit_of_measure_id' => (string) $item->unit_of_measure_id,
                'ordered_quantity' => (string) $item->remainingQuantity(),
                'unit_price' => (string) $item->estimated_unit_price,
                'tax_rate' => '0',
                'discount_amount' => '0',
                'required_by' => $item->required_by?->toDateString() ?? '',
                'cost_center_id' => $requisition->cost_center_id !== null ? (string) $requisition->cost_center_id : '',
                'project_id' => $requisition->project_id !== null ? (string) $requisition->project_id : '',
                'purchase_requisition_item_id' => $item->id,
            ])->values()->all();
    }

    /**
     * @return Collection<int, Warehouse>
     */
    private function accessibleWarehouses()
    {
        $query = Warehouse::query()->where('is_active', true)->orderBy('name');

        if (! auth()->user()->hasGlobalAccess()) {
            $query->whereIn('id', auth()->user()->activeWarehouseIds());
        }

        return $query->get();
    }

    /**
     * @return array<string, mixed>
     */
    private function blankItem(): array
    {
        return [
            'item_id' => '',
            'unit_of_measure_id' => '',
            'ordered_quantity' => '1',
            'unit_price' => '0',
            'tax_rate' => '0',
            'discount_amount' => '0',
            'required_by' => '',
            'cost_center_id' => '',
            'project_id' => '',
            'purchase_requisition_item_id' => null,
        ];
    }

    public function render()
    {
        return view('livewire.procurement.orders.form', [
            'suppliers' => Supplier::query()->approved()->orderBy('name')->get(),
            'warehouses' => $this->accessibleWarehouses(),
            'catalogItems' => Item::query()->where('is_active', true)->orderBy('name')->get(),
            'units' => UnitOfMeasure::query()->where('is_active', true)->orderBy('name')->get(),
            'requisitions' => PurchaseRequisition::query()
                ->whereIn('status', ['approved', 'partially_approved'])
                ->orderBy('document_number')
                ->get(),
            'quotations' => SupplierQuotation::query()
                ->where('status', 'accepted')
                ->orderBy('document_number')
                ->get(),
            'allowUnsourced' => (bool) config('procurement.allow_unsourced_orders', false),
            'status' => $this->orderId !== null ? PurchaseOrder::find($this->orderId)?->status : PurchaseOrderStatus::Draft,
        ]);
    }
}
