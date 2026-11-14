<?php

namespace App\Livewire\Distribution\Transfers;

use App\Domain\Catalog\Models\Item;
use App\Domain\Distribution\Models\StockTransfer;
use App\Domain\Distribution\Services\StockTransferService;
use App\DTOs\Distribution\StockTransferData;
use App\Livewire\Concerns\HandlesDomainActions;
use App\Livewire\Concerns\UsesDistributionOptions;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Stock Transfer')]
class Form extends Component
{
    use HandlesDomainActions, UsesDistributionOptions;

    public ?int $transferId = null;

    /** @var array<string, mixed> */
    public array $form = [];

    /** @var list<array<string, mixed>> */
    public array $items = [];

    public function mount(?StockTransfer $transfer = null): void
    {
        if ($transfer !== null && $transfer->exists) {
            $this->authorize('update', $transfer);

            $this->transferId = $transfer->id;
            $this->form = [
                'source_warehouse_id' => (string) $transfer->source_warehouse_id,
                'destination_warehouse_id' => (string) $transfer->destination_warehouse_id,
                'reason' => (string) $transfer->reason,
                'required_by' => $transfer->required_by?->toDateString() ?? '',
            ];

            $this->items = $transfer->items->map(fn ($item): array => [
                'item_id' => (string) $item->item_id,
                'unit_of_measure_id' => (string) $item->unit_of_measure_id,
                'requested_quantity' => (string) $item->requested_quantity,
            ])->all();

            return;
        }

        $this->authorize('create', StockTransfer::class);

        $warehouses = $this->accessibleWarehouses();

        $this->form = [
            'source_warehouse_id' => (string) ($warehouses->get(0)?->id ?? ''),
            'destination_warehouse_id' => (string) ($warehouses->get(1)?->id ?? ''),
            'reason' => '',
            'required_by' => now()->addDays(7)->toDateString(),
        ];

        $this->items = [$this->blankItem()];
    }

    public function updated(string $property): void
    {
        if (! str_ends_with($property, '.item_id')) {
            return;
        }

        $parts = explode('.', $property);

        if (count($parts) !== 3) {
            return;
        }

        $index = (int) $parts[1];
        $item = Item::query()->with('baseUnit')->find((int) ($this->items[$index]['item_id'] ?? 0));

        if ($item !== null) {
            $this->items[$index]['unit_of_measure_id'] = (string) $item->base_unit_of_measure_id;
        }
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

    public function save(StockTransferService $service)
    {
        $transfer = $this->transferId !== null
            ? StockTransfer::query()->findOrFail($this->transferId)
            : null;

        $transfer !== null
            ? $this->authorize('update', $transfer)
            : $this->authorize('create', StockTransfer::class);

        $this->validate([
            'form.source_warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'form.destination_warehouse_id' => ['required', 'integer', 'exists:warehouses,id', 'different:form.source_warehouse_id'],
            'form.reason' => ['required', 'string', 'max:2000'],
            'form.required_by' => ['nullable', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'integer', 'exists:items,id'],
            'items.*.unit_of_measure_id' => ['required', 'integer', 'exists:units_of_measure,id'],
            'items.*.requested_quantity' => ['required', 'numeric', 'gt:0'],
        ]);

        $data = StockTransferData::fromArray([
            ...$this->form,
            'requested_by' => auth()->id(),
            'items' => array_map(fn (array $item): array => [
                'item_id' => (int) $item['item_id'],
                'unit_of_measure_id' => (int) $item['unit_of_measure_id'],
                'requested_quantity' => (string) $item['requested_quantity'],
            ], $this->items),
        ]);

        $saved = null;

        $ok = $this->runDomainAction(
            function () use ($service, $transfer, $data, &$saved): void {
                $saved = $transfer !== null
                    ? $service->update($transfer, $data, auth()->user())
                    : $service->create($data, auth()->user());
            },
            $transfer !== null ? 'Stock transfer updated.' : 'Stock transfer created as a draft.',
        );

        if ($ok && $saved !== null) {
            return redirect()->route('distribution.transfers.show', $saved);
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function blankItem(): array
    {
        return [
            'item_id' => '',
            'unit_of_measure_id' => '',
            'requested_quantity' => '1',
        ];
    }

    public function render()
    {
        return view('livewire.distribution.transfers.form', [
            'warehouses' => $this->accessibleWarehouses(),
            'catalogItems' => $this->catalogItems(),
            'units' => $this->unitOptions(),
        ]);
    }
}
