<?php

namespace App\Livewire\Stocktaking\Adjustments;

use App\Domain\Catalog\Models\Item;
use App\Domain\Catalog\Models\ItemBatch;
use App\Domain\Inventory\Models\StockDimension;
use App\Domain\Stocktaking\Models\StockAdjustment;
use App\Domain\Stocktaking\Services\StockAdjustmentService;
use App\DTOs\Stocktaking\StockAdjustmentData;
use App\DTOs\Stocktaking\StockAdjustmentItemData;
use App\Enums\AdjustmentReason;
use App\Enums\AdjustmentType;
use App\Livewire\Concerns\HandlesDomainActions;
use App\Livewire\Concerns\UsesDistributionOptions;
use App\Support\Decimal;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Stock Adjustment')]
class Form extends Component
{
    use HandlesDomainActions, UsesDistributionOptions;

    public ?int $adjustmentId = null;

    /** @var array<string, mixed> */
    public array $form = [];

    /** @var list<array<string, mixed>> */
    public array $items = [];

    public function mount(?StockAdjustment $adjustment = null): void
    {
        if ($adjustment !== null && $adjustment->exists) {
            $this->authorize('update', $adjustment);

            $adjustment->load('items');

            $this->adjustmentId = $adjustment->id;
            $this->form = [
                'warehouse_id' => (string) $adjustment->warehouse_id,
                'adjustment_type' => $adjustment->adjustment_type->value,
                'reason_code' => $adjustment->reason_code->value,
                'reason' => (string) $adjustment->reason,
            ];

            $this->items = $adjustment->items->map(fn ($item): array => [
                'item_id' => (string) $item->item_id,
                'unit_of_measure_id' => (string) $item->unit_of_measure_id,
                'quantity' => $item->absoluteQuantity(),
                'unit_cost' => (string) $item->unit_cost,
                'batch_id' => $item->batch_id !== null ? (string) $item->batch_id : '',
                'reason' => (string) ($item->reason ?? ''),
            ])->all();

            return;
        }

        $this->authorize('create', StockAdjustment::class);

        $warehouses = $this->accessibleWarehouses();

        $this->form = [
            'warehouse_id' => (string) ($warehouses->first()->id ?? ''),
            'adjustment_type' => AdjustmentType::Increase->value,
            'reason_code' => AdjustmentReason::CountCorrection->value,
            'reason' => '',
        ];

        $this->addItem();
    }

    public function updated(string $property): void
    {
        if (preg_match('/^items\.(\d+)\.item_id$/', $property, $matches) !== 1) {
            return;
        }

        $index = (int) $matches[1];

        $item = Item::query()->with('baseUnit')->find($this->items[$index]['item_id'] ?? null);

        if ($item !== null) {
            $this->items[$index]['unit_of_measure_id'] = (string) $item->base_unit_of_measure_id;
            $this->items[$index]['unit_cost'] = (string) ($item->standard_cost ?? '0');
        }

        $this->items[$index]['batch_id'] = '';
    }

    public function addItem(): void
    {
        $this->items[] = [
            'item_id' => '',
            'unit_of_measure_id' => '',
            'quantity' => '',
            'unit_cost' => '0',
            'batch_id' => '',
            'reason' => '',
        ];
    }

    public function removeItem(int $index): void
    {
        unset($this->items[$index]);

        $this->items = array_values($this->items);
    }

    public function save(StockAdjustmentService $service)
    {
        $adjustment = $this->adjustmentId !== null
            ? StockAdjustment::query()->findOrFail($this->adjustmentId)
            : null;

        $adjustment !== null
            ? $this->authorize('update', $adjustment)
            : $this->authorize('create', StockAdjustment::class);

        $this->validate([
            'form.warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'form.adjustment_type' => ['required', 'string', 'in:increase,decrease'],
            'form.reason_code' => ['required', 'string', 'in:'.implode(',', AdjustmentReason::values())],
            'form.reason' => ['required', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'integer', 'exists:items,id'],
            'items.*.unit_of_measure_id' => ['required', 'integer', 'exists:units_of_measure,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
            'items.*.batch_id' => ['nullable', 'integer', 'exists:item_batches,id'],
        ]);

        $type = AdjustmentType::from((string) $this->form['adjustment_type']);
        $warehouseId = (int) $this->form['warehouse_id'];
        $dimension = StockDimension::resolve($warehouseId);

        $lines = [];

        foreach ($this->items as $item) {
            $quantity = Decimal::normalize((string) $item['quantity']);

            $lines[] = new StockAdjustmentItemData(
                itemId: (int) $item['item_id'],
                unitOfMeasureId: (int) $item['unit_of_measure_id'],
                stockDimensionId: $dimension->id,
                quantityDelta: $type === AdjustmentType::Decrease
                    ? Decimal::subtract('0', $quantity)
                    : $quantity,
                unitCost: Decimal::normalize((string) ($item['unit_cost'] ?? '0'), Decimal::COST_SCALE),
                batchId: ($item['batch_id'] ?? '') !== '' ? (int) $item['batch_id'] : null,
                reason: ($item['reason'] ?? '') !== '' ? (string) $item['reason'] : null,
            );
        }

        $data = new StockAdjustmentData(
            warehouseId: $warehouseId,
            adjustmentType: $type,
            reasonCode: AdjustmentReason::from((string) $this->form['reason_code']),
            reason: (string) $this->form['reason'],
            items: $lines,
        );

        $saved = null;

        $ok = $this->runDomainAction(
            function () use ($service, $adjustment, $data, &$saved): void {
                $saved = $adjustment !== null
                    ? $service->update($adjustment, $data, auth()->user())
                    : $service->create($data, auth()->user());
            },
            $adjustment !== null ? 'Stock adjustment updated.' : 'Stock adjustment created as a draft.',
        );

        if ($ok && $saved !== null) {
            return redirect()->route('stocktaking.adjustments.show', $saved);
        }

        return null;
    }

    public function render()
    {
        $itemIds = collect($this->items)
            ->pluck('item_id')
            ->filter(fn ($value): bool => $value !== '' && $value !== null)
            ->map(fn ($value): int => (int) $value)
            ->unique()
            ->values();

        $batches = ItemBatch::query()
            ->whereIn('item_id', $itemIds->all())
            ->orderBy('batch_number')
            ->get()
            ->groupBy('item_id')
            ->map(fn ($group) => $group->map(fn (ItemBatch $batch): array => [
                'id' => (int) $batch->id,
                'label' => (string) $batch->batch_number,
            ])->all())
            ->all();

        return view('livewire.stocktaking.adjustments.form', [
            'warehouses' => $this->accessibleWarehouses(),
            'catalogItems' => $this->catalogItems(),
            'units' => $this->unitOptions(),
            'reasons' => AdjustmentReason::cases(),
            'types' => AdjustmentType::cases(),
            'batches' => $batches,
        ]);
    }
}
