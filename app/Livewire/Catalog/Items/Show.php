<?php

namespace App\Livewire\Catalog\Items;

use App\Domain\Catalog\Models\Item;
use App\Domain\Catalog\Models\ItemSpecification;
use App\Domain\Catalog\Models\ItemUnit;
use App\Domain\Catalog\Models\UnitOfMeasure;
use App\Domain\Catalog\Services\CatalogService;
use App\Domain\Supplier\Models\Supplier;
use App\Livewire\Concerns\HandlesDomainActions;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Spatie\Activitylog\Models\Activity;

#[Layout('layouts.app')]
#[Title('Item')]
class Show extends Component
{
    use HandlesDomainActions;

    public Item $item;

    /** @var array<string, mixed> */
    public array $specForm = [];

    /** @var array<string, mixed> */
    public array $unitForm = [];

    /** @var array<string, mixed> */
    public array $batchForm = [];

    /** @var array<string, mixed> */
    public array $serialForm = [];

    public function mount(Item $item): void
    {
        $this->item = $item;
        $this->authorize('view', $this->item);

        $this->resetSpecForm();
        $this->resetUnitForm();
        $this->resetBatchForm();
        $this->resetSerialForm();
    }

    public function resetSpecForm(): void
    {
        $this->specForm = [
            'attribute' => '',
            'value' => '',
            'unit_of_measure_id' => '',
        ];
        $this->resetValidation();
    }

    public function resetUnitForm(): void
    {
        $this->unitForm = [
            'unit_of_measure_id' => '',
            'conversion_factor' => '1.000000',
        ];
        $this->resetValidation();
    }

    public function resetBatchForm(): void
    {
        $this->batchForm = [
            'batch_number' => '',
            'manufacture_date' => '',
            'expiry_date' => '',
            'supplier_id' => '',
        ];
        $this->resetValidation();
    }

    public function resetSerialForm(): void
    {
        $this->serialForm = [
            'serial_number' => '',
            'batch_id' => '',
        ];
        $this->resetValidation();
    }

    public function addSpecification(CatalogService $service): void
    {
        $this->authorize('update', $this->item);

        $this->validate([
            'specForm.attribute' => ['required', 'string', 'max:100'],
            'specForm.value' => ['required', 'string', 'max:255'],
            'specForm.unit_of_measure_id' => ['nullable', 'integer', Rule::exists('units_of_measure', 'id')],
        ]);

        $specifications = $this->currentSpecifications();
        $specifications[] = [
            'attribute' => $this->specForm['attribute'],
            'value' => $this->specForm['value'],
            'unit_of_measure_id' => $this->specForm['unit_of_measure_id'] !== '' ? (int) $this->specForm['unit_of_measure_id'] : null,
        ];

        if ($this->runDomainAction(fn () => $service->syncSpecifications($this->item, $specifications), 'Specification added.')) {
            $this->resetSpecForm();
        }
    }

    public function deleteSpecification(int $specificationId, CatalogService $service): void
    {
        $this->authorize('update', $this->item);

        $specifications = $this->item->specifications()
            ->orderBy('sort_order')
            ->get()
            ->reject(fn (ItemSpecification $specification): bool => $specification->id === $specificationId)
            ->map(fn (ItemSpecification $specification) => [
                'attribute' => $specification->attribute,
                'value' => $specification->value,
                'unit_of_measure_id' => $specification->unit_of_measure_id,
            ])
            ->values()
            ->all();

        $this->runDomainAction(fn () => $service->syncSpecifications($this->item, $specifications), 'Specification removed.');
    }

    public function addUnit(CatalogService $service): void
    {
        $this->authorize('update', $this->item);

        $this->validate([
            'unitForm.unit_of_measure_id' => ['required', 'integer', Rule::exists('units_of_measure', 'id')],
            'unitForm.conversion_factor' => ['required', 'numeric', 'gt:0'],
        ]);

        if ((int) $this->unitForm['unit_of_measure_id'] === $this->item->base_unit_of_measure_id) {
            $this->error('The base unit is added automatically.');

            return;
        }

        $units = $this->currentUnits();
        $units[] = [
            'unit_of_measure_id' => (int) $this->unitForm['unit_of_measure_id'],
            'conversion_factor' => (string) $this->unitForm['conversion_factor'],
        ];

        if ($this->runDomainAction(fn () => $service->syncUnits($this->item, $units), 'Alternate unit added.')) {
            $this->resetUnitForm();
        }
    }

    public function deleteUnit(int $itemUnitId, CatalogService $service): void
    {
        $this->authorize('update', $this->item);

        $unit = $this->item->units()->findOrFail($itemUnitId);

        if ($unit->is_base) {
            $this->error('The base unit cannot be removed.');

            return;
        }

        $units = collect($this->currentUnits())
            ->reject(fn (array $unitData) => (int) $unitData['unit_of_measure_id'] === $unit->unit_of_measure_id)
            ->values()
            ->all();

        $this->runDomainAction(fn () => $service->syncUnits($this->item, $units), 'Alternate unit removed.');
    }

    public function addBatch(CatalogService $service): void
    {
        $this->authorize('update', $this->item);

        if (! $this->item->tracksBatches()) {
            $this->error('This item does not track batches.');

            return;
        }

        $this->validate([
            'batchForm.batch_number' => ['required', 'string', 'max:100', Rule::unique('item_batches', 'batch_number')->where('item_id', $this->item->id)],
            'batchForm.manufacture_date' => ['nullable', 'date'],
            'batchForm.expiry_date' => ['nullable', 'date', 'after_or_equal:batchForm.manufacture_date'],
            'batchForm.supplier_id' => ['nullable', 'integer', Rule::exists('suppliers', 'id')],
        ]);

        $manufactureDate = $this->batchForm['manufacture_date'] !== '' ? $this->batchForm['manufacture_date'] : null;
        $expiryDate = $this->batchForm['expiry_date'] !== '' ? $this->batchForm['expiry_date'] : null;
        $supplierId = $this->batchForm['supplier_id'] !== '' ? (int) $this->batchForm['supplier_id'] : null;

        if ($this->runDomainAction(
            fn () => $service->createBatch($this->item, $this->batchForm['batch_number'], $manufactureDate, $expiryDate, $supplierId),
            'Batch added.',
        )) {
            $this->resetBatchForm();
        }
    }

    public function addSerial(CatalogService $service): void
    {
        $this->authorize('update', $this->item);

        if (! $this->item->tracksSerials()) {
            $this->error('This item does not track serial numbers.');

            return;
        }

        $this->validate([
            'serialForm.serial_number' => ['required', 'string', 'max:150'],
            'serialForm.batch_id' => ['nullable', 'integer', Rule::exists('item_batches', 'id')->where('item_id', $this->item->id)],
        ]);

        $batch = $this->serialForm['batch_id'] !== ''
            ? $this->item->batches()->findOrFail((int) $this->serialForm['batch_id'])
            : null;

        if ($this->runDomainAction(
            fn () => $service->createSerial($this->item, $this->serialForm['serial_number'], $batch),
            'Serial number added.',
        )) {
            $this->resetSerialForm();
        }
    }

    public function render()
    {
        $this->item->refresh();

        return view('livewire.catalog.items.show', [
            'specifications' => $this->item->specifications()->with('unitOfMeasure')->orderBy('sort_order')->get(),
            'itemUnits' => $this->item->units()->with('unitOfMeasure')->orderByDesc('is_base')->get(),
            'batches' => $this->item->batches()->with('supplier')->latest('id')->get(),
            'serials' => $this->item->serialNumbers()->with(['batch', 'currentWarehouse'])->latest('id')->limit(25)->get(),
            'units' => UnitOfMeasure::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name', 'symbol']),
            'suppliers' => Supplier::query()->operational()->orderBy('name')->get(['id', 'code', 'name']),
            'activity' => Activity::query()
                ->where('subject_type', $this->item->getMorphClass())
                ->where('subject_id', $this->item->id)
                ->with('causer')
                ->latest()
                ->limit(10)
                ->get(),
        ]);
    }

    /**
     * @return list<array{attribute: string, value: string, unit_of_measure_id: int|null}>
     */
    private function currentSpecifications(): array
    {
        return $this->item->specifications()
            ->orderBy('sort_order')
            ->get()
            ->map(fn (ItemSpecification $specification) => [
                'attribute' => $specification->attribute,
                'value' => $specification->value,
                'unit_of_measure_id' => $specification->unit_of_measure_id,
            ])
            ->all();
    }

    /**
     * @return list<array{unit_of_measure_id: int, conversion_factor: string}>
     */
    private function currentUnits(): array
    {
        return $this->item->units()
            ->where('is_base', false)
            ->get()
            ->map(fn (ItemUnit $unit) => [
                'unit_of_measure_id' => $unit->unit_of_measure_id,
                'conversion_factor' => (string) $unit->conversion_factor,
            ])
            ->all();
    }
}
