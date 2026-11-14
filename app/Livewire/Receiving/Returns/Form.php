<?php

namespace App\Livewire\Receiving\Returns;

use App\Domain\Catalog\Models\Item;
use App\Domain\Receiving\Models\GoodsReceivedNote;
use App\Domain\Receiving\Models\SupplierReturn;
use App\Domain\Receiving\Services\SupplierReturnService;
use App\Domain\Supplier\Models\Supplier;
use App\Domain\Warehouse\Models\Warehouse;
use App\DTOs\Receiving\SupplierReturnData;
use App\Enums\DeliveryCondition;
use App\Enums\GoodsReceivedNoteStatus;
use App\Livewire\Concerns\HandlesDomainActions;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Supplier Return')]
class Form extends Component
{
    use HandlesDomainActions;

    public ?int $returnId = null;

    /** @var array<string, mixed> */
    public array $form = [];

    /** @var list<array<string, mixed>> */
    public array $items = [];

    public function mount(?SupplierReturn $supplierReturn = null): void
    {
        $this->authorize('viewAny', SupplierReturn::class);

        if ($supplierReturn !== null && $supplierReturn->exists) {
            $this->authorize('update', $supplierReturn);

            $this->returnId = $supplierReturn->id;
            $this->form = [
                'supplier_id' => (string) $supplierReturn->supplier_id,
                'grn_id' => $supplierReturn->grn_id !== null ? (string) $supplierReturn->grn_id : '',
                'warehouse_id' => (string) $supplierReturn->warehouse_id,
                'reason' => (string) $supplierReturn->reason,
                'return_date' => $supplierReturn->return_date?->toDateString() ?? now()->toDateString(),
                'credit_note_number' => (string) $supplierReturn->credit_note_number,
            ];

            $this->items = $supplierReturn->items->map(fn ($item): array => [
                'item_id' => (string) $item->item_id,
                'unit_of_measure_id' => (string) $item->unit_of_measure_id,
                'quantity' => (string) $item->quantity,
                'unit_cost' => (string) $item->unit_cost,
                'batch_id' => $item->batch_id !== null ? (string) $item->batch_id : '',
                'serial_numbers' => implode(',', $item->serial_numbers ?? []),
                'condition' => $item->condition?->value ?? DeliveryCondition::Good->value,
            ])->all();

            return;
        }

        $this->authorize('create', SupplierReturn::class);

        $warehouses = $this->accessibleWarehouses();

        $this->form = [
            'supplier_id' => '',
            'grn_id' => '',
            'warehouse_id' => (string) ($warehouses->first()->id ?? ''),
            'reason' => '',
            'return_date' => now()->toDateString(),
            'credit_note_number' => '',
        ];
        $this->items = [$this->blankLine()];
    }

    public function updatedFormGrnId(): void
    {
        $grn = GoodsReceivedNote::query()
            ->with('items.item', 'items.unitOfMeasure')
            ->find((int) $this->form['grn_id']);

        if ($grn === null) {
            return;
        }

        $this->form['supplier_id'] = (string) $grn->supplier_id;
        $this->form['warehouse_id'] = (string) $grn->warehouse_id;

        $this->items = $grn->items
            ->filter(fn ($item) => (float) $item->accepted_quantity > 0)
            ->map(fn ($item): array => [
                'item_id' => (string) $item->item_id,
                'unit_of_measure_id' => (string) $item->unit_of_measure_id,
                'quantity' => (string) $item->accepted_quantity,
                'unit_cost' => (string) $item->unit_cost,
                'batch_id' => $item->batch_id !== null ? (string) $item->batch_id : '',
                'serial_numbers' => '',
                'condition' => DeliveryCondition::Good->value,
            ])
            ->values()
            ->all();

        if ($this->items === []) {
            $this->items = [$this->blankLine()];
        }
    }

    public function updated(string $property): void
    {
        if (! str_ends_with($property, '.item_id')) {
            return;
        }

        $index = (int) explode('.', $property)[1];
        $item = Item::query()->with('baseUnit')->find((int) ($this->items[$index]['item_id'] ?? 0));

        if ($item === null) {
            return;
        }

        $this->items[$index]['unit_of_measure_id'] = (string) $item->base_unit_of_measure_id;
        $this->items[$index]['unit_cost'] = (string) $item->standard_cost;
    }

    public function addLine(): void
    {
        $this->items[] = $this->blankLine();
    }

    public function removeLine(int $index): void
    {
        unset($this->items[$index]);

        $this->items = array_values($this->items);

        if ($this->items === []) {
            $this->items = [$this->blankLine()];
        }
    }

    public function save(SupplierReturnService $service)
    {
        $return = $this->returnId !== null ? SupplierReturn::findOrFail($this->returnId) : null;

        $return !== null
            ? $this->authorize('update', $return)
            : $this->authorize('create', SupplierReturn::class);

        $this->validate([
            'form.supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'form.grn_id' => ['nullable', 'integer', 'exists:goods_received_notes,id'],
            'form.warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'form.reason' => ['required', 'string', 'max:2000'],
            'form.return_date' => ['nullable', 'date'],
            'form.credit_note_number' => ['nullable', 'string', 'max:80'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'integer', 'exists:items,id'],
            'items.*.unit_of_measure_id' => ['required', 'integer', 'exists:units_of_measure,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0'],
            'items.*.batch_id' => ['nullable', 'integer', 'exists:item_batches,id'],
        ]);

        $data = SupplierReturnData::fromArray([
            ...$this->form,
            'created_by' => auth()->id(),
            'items' => array_map(fn (array $item): array => [
                'item_id' => (int) $item['item_id'],
                'unit_of_measure_id' => (int) $item['unit_of_measure_id'],
                'quantity' => (string) $item['quantity'],
                'unit_cost' => (string) $item['unit_cost'],
                'batch_id' => $item['batch_id'] !== '' ? (int) $item['batch_id'] : null,
                'serial_numbers' => array_values(array_filter(array_map('trim', explode(',', (string) ($item['serial_numbers'] ?? ''))))),
                'condition' => $item['condition'] ?? DeliveryCondition::Good->value,
            ], $this->items),
        ]);

        $saved = null;

        $ok = $this->runDomainAction(
            function () use ($service, $data, $return, &$saved): void {
                $saved = $return !== null
                    ? $service->update($return, $data, auth()->user())
                    : $service->create($data, auth()->user());
            },
            $return !== null ? 'Supplier return updated.' : 'Supplier return created.',
        );

        if ($ok && $saved !== null) {
            return redirect()->route('receiving.returns.show', $saved);
        }

        return null;
    }

    public function render()
    {
        $items = Item::query()->with('baseUnit')->where('is_active', true)->orderBy('name')->get();

        return view('livewire.receiving.returns.form', [
            'suppliers' => Supplier::query()->orderBy('name')->get(),
            'warehouses' => $this->accessibleWarehouses(),
            'catalogItems' => $items,
            'grns' => GoodsReceivedNote::query()
                ->where('status', GoodsReceivedNoteStatus::Posted->value)
                ->visibleTo(auth()->user())
                ->latest('id')
                ->get(),
            'batchesByItem' => $items
                ->mapWithKeys(fn (Item $item) => [$item->id => $item->batches()->orderByDesc('id')->get()])
                ->all(),
            'conditionOptions' => DeliveryCondition::cases(),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function blankLine(): array
    {
        return [
            'item_id' => '',
            'unit_of_measure_id' => '',
            'quantity' => '1',
            'unit_cost' => '0',
            'batch_id' => '',
            'serial_numbers' => '',
            'condition' => DeliveryCondition::Good->value,
        ];
    }

    private function accessibleWarehouses()
    {
        $query = Warehouse::query()->where('is_active', true)->orderBy('name');

        if (! auth()->user()->hasGlobalAccess()) {
            $query->whereIn('id', auth()->user()->activeWarehouseIds());
        }

        return $query->get();
    }
}
