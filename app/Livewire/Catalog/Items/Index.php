<?php

namespace App\Livewire\Catalog\Items;

use App\Domain\Catalog\Models\Item;
use App\Domain\Catalog\Models\ItemCategory;
use App\Domain\Catalog\Models\UnitOfMeasure;
use App\Domain\Catalog\Services\CatalogService;
use App\DTOs\Catalog\ItemData;
use App\Enums\ItemTrackingType;
use App\Enums\ValuationMethod;
use App\Livewire\Concerns\HandlesDomainActions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Catalog Items')]
class Index extends Component
{
    use HandlesDomainActions, WithPagination;

    public string $search = '';

    public string $categoryFilter = '';

    public string $trackingFilter = '';

    public string $statusFilter = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    /** @var array<string, mixed> */
    public array $form = [];

    public bool $showDeleteModal = false;

    public ?int $deletingId = null;

    public string $deletingName = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Item::class);
        $this->resetForm();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedCategoryFilter(): void
    {
        $this->resetPage();
    }

    public function updatedTrackingFilter(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function resetForm(): void
    {
        $this->editingId = null;
        $this->form = [
            'category_id' => '',
            'code' => '',
            'name' => '',
            'description' => '',
            'brand' => '',
            'model' => '',
            'manufacturer_part_number' => '',
            'tracking_type' => ItemTrackingType::None->value,
            'base_unit_of_measure_id' => '',
            'minimum_stock' => '0.0000',
            'maximum_stock' => '',
            'reorder_level' => '0.0000',
            'reorder_quantity' => '0.0000',
            'standard_cost' => '0.0000',
            'valuation_method' => ValuationMethod::WeightedAverage->value,
            'requires_quality_inspection' => true,
            'shelf_life_days' => '',
            'is_active' => true,
        ];
        $this->resetValidation();
    }

    public function openCreate(): void
    {
        $this->authorize('create', Item::class);
        $this->resetForm();
        $this->showForm = true;
    }

    public function openEdit(int $itemId): void
    {
        $item = Item::findOrFail($itemId);
        $this->authorize('update', $item);

        $this->editingId = $item->id;
        $this->form = [
            'category_id' => (string) $item->category_id,
            'code' => $item->code,
            'name' => $item->name,
            'description' => (string) $item->description,
            'brand' => (string) $item->brand,
            'model' => (string) $item->model,
            'manufacturer_part_number' => (string) $item->manufacturer_part_number,
            'tracking_type' => $item->tracking_type->value,
            'base_unit_of_measure_id' => (string) $item->base_unit_of_measure_id,
            'minimum_stock' => (string) $item->minimum_stock,
            'maximum_stock' => $item->maximum_stock !== null ? (string) $item->maximum_stock : '',
            'reorder_level' => (string) $item->reorder_level,
            'reorder_quantity' => (string) $item->reorder_quantity,
            'standard_cost' => (string) $item->standard_cost,
            'valuation_method' => $item->valuation_method->value,
            'requires_quality_inspection' => $item->requires_quality_inspection,
            'shelf_life_days' => $item->shelf_life_days !== null ? (string) $item->shelf_life_days : '',
            'is_active' => $item->is_active,
        ];
        $this->resetValidation();
        $this->showForm = true;
    }

    public function save(CatalogService $service): void
    {
        $item = $this->editingId !== null ? Item::findOrFail($this->editingId) : null;

        $item !== null
            ? $this->authorize('update', $item)
            : $this->authorize('create', Item::class);

        $this->validate([
            'form.category_id' => ['required', 'integer', Rule::exists('item_categories', 'id')],
            'form.code' => ['required', 'string', 'max:30', Rule::unique('items', 'code')->ignore($item?->id)],
            'form.name' => ['required', 'string', 'max:200'],
            'form.description' => ['nullable', 'string', 'max:5000'],
            'form.brand' => ['nullable', 'string', 'max:100'],
            'form.model' => ['nullable', 'string', 'max:100'],
            'form.manufacturer_part_number' => ['nullable', 'string', 'max:100'],
            'form.tracking_type' => ['required', Rule::enum(ItemTrackingType::class)],
            'form.base_unit_of_measure_id' => ['required', 'integer', Rule::exists('units_of_measure', 'id')],
            'form.minimum_stock' => ['required', 'numeric', 'min:0'],
            'form.maximum_stock' => ['nullable', 'numeric', 'min:0'],
            'form.reorder_level' => ['required', 'numeric', 'min:0'],
            'form.reorder_quantity' => ['required', 'numeric', 'min:0'],
            'form.standard_cost' => ['required', 'numeric', 'min:0'],
            'form.valuation_method' => ['required', Rule::enum(ValuationMethod::class)],
            'form.requires_quality_inspection' => ['boolean'],
            'form.shelf_life_days' => ['nullable', 'integer', 'min:0'],
            'form.is_active' => ['boolean'],
        ]);

        $payload = $this->form;
        $payload['maximum_stock'] = $this->form['maximum_stock'] !== '' ? $this->form['maximum_stock'] : null;
        $payload['shelf_life_days'] = $this->form['shelf_life_days'] !== '' ? (int) $this->form['shelf_life_days'] : null;
        $payload['specifications'] = $item?->specifications()
            ->get()
            ->map(fn ($specification) => [
                'attribute' => $specification->attribute,
                'value' => $specification->value,
                'unit_of_measure_id' => $specification->unit_of_measure_id,
            ])
            ->all() ?? [];
        $payload['units'] = $item?->units()
            ->where('is_base', false)
            ->get()
            ->map(fn ($unit) => [
                'unit_of_measure_id' => $unit->unit_of_measure_id,
                'conversion_factor' => (string) $unit->conversion_factor,
            ])
            ->all() ?? [];

        $data = ItemData::fromArray($payload);

        $saved = $this->runDomainAction(
            fn () => $item !== null ? $service->updateItem($item, $data) : $service->createItem($data),
            $item !== null ? 'Item updated.' : 'Item created.',
        );

        if ($saved) {
            $this->showForm = false;
            $this->resetForm();
        }
    }

    public function confirmDelete(int $itemId): void
    {
        $item = Item::findOrFail($itemId);
        $this->authorize('delete', $item);

        $this->deletingId = $item->id;
        $this->deletingName = $item->name;
        $this->showDeleteModal = true;
    }

    public function delete(CatalogService $service): void
    {
        $item = Item::findOrFail($this->deletingId);
        $this->authorize('delete', $item);

        if ($this->runDomainAction(fn () => $service->deleteItem($item), 'Item deleted.')) {
            $this->showDeleteModal = false;
            $this->deletingId = null;
            $this->deletingName = '';
            $this->resetPage();
        }
    }

    public function render()
    {
        $items = Item::query()
            ->with(['category', 'baseUnit'])
            ->withCount(['batches', 'serialNumbers'])
            ->search($this->search)
            ->when($this->categoryFilter !== '', fn (Builder $query) => $query->where('category_id', (int) $this->categoryFilter))
            ->when($this->trackingFilter !== '', fn (Builder $query) => $query->where('tracking_type', $this->trackingFilter))
            ->when($this->statusFilter !== '', fn (Builder $query) => $query->where('is_active', $this->statusFilter === 'active'))
            ->orderBy('name')
            ->paginate(15);

        return view('livewire.catalog.items.index', [
            'items' => $items,
            'categories' => ItemCategory::query()->orderBy('name')->get(['id', 'code', 'name']),
            'units' => UnitOfMeasure::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name', 'symbol']),
            'trackingOptions' => ItemTrackingType::cases(),
            'valuationOptions' => ValuationMethod::cases(),
        ]);
    }
}
