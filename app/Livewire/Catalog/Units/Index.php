<?php

namespace App\Livewire\Catalog\Units;

use App\Domain\Catalog\Models\UnitOfMeasure;
use App\Domain\Catalog\Services\CatalogService;
use App\DTOs\Catalog\UnitData;
use App\Enums\UnitOfMeasureType;
use App\Livewire\Concerns\HandlesDomainActions;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Units of Measure')]
class Index extends Component
{
    use HandlesDomainActions, WithPagination;

    public string $search = '';

    public string $typeFilter = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    /** @var array<string, mixed> */
    public array $form = [];

    public bool $showDeleteModal = false;

    public ?int $deletingId = null;

    public string $deletingName = '';

    public function mount(): void
    {
        $this->authorize('viewAny', UnitOfMeasure::class);
        $this->resetForm();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedTypeFilter(): void
    {
        $this->resetPage();
    }

    public function resetForm(): void
    {
        $this->editingId = null;
        $this->form = [
            'code' => '',
            'name' => '',
            'symbol' => '',
            'type' => UnitOfMeasureType::Quantity->value,
            'decimal_places' => 4,
            'is_active' => true,
        ];
        $this->resetValidation();
    }

    public function openCreate(): void
    {
        $this->authorize('create', UnitOfMeasure::class);
        $this->resetForm();
        $this->showForm = true;
    }

    public function openEdit(int $unitId): void
    {
        $unit = UnitOfMeasure::findOrFail($unitId);
        $this->authorize('update', $unit);

        $this->editingId = $unit->id;
        $this->form = [
            'code' => $unit->code,
            'name' => $unit->name,
            'symbol' => $unit->symbol,
            'type' => $unit->type->value,
            'decimal_places' => $unit->decimal_places,
            'is_active' => $unit->is_active,
        ];
        $this->resetValidation();
        $this->showForm = true;
    }

    public function save(CatalogService $service): void
    {
        $unit = $this->editingId !== null ? UnitOfMeasure::findOrFail($this->editingId) : null;

        $unit !== null
            ? $this->authorize('update', $unit)
            : $this->authorize('create', UnitOfMeasure::class);

        $this->validate([
            'form.code' => ['required', 'string', 'max:20', Rule::unique('units_of_measure', 'code')->ignore($unit?->id)],
            'form.name' => ['required', 'string', 'max:100'],
            'form.symbol' => ['required', 'string', 'max:20'],
            'form.type' => ['required', Rule::enum(UnitOfMeasureType::class)],
            'form.decimal_places' => ['required', 'integer', 'min:0', 'max:6'],
            'form.is_active' => ['boolean'],
        ]);

        $data = UnitData::fromArray($this->form);

        $saved = $this->runDomainAction(
            fn () => $unit !== null ? $service->updateUnit($unit, $data) : $service->createUnit($data),
            $unit !== null ? 'Unit updated.' : 'Unit created.',
        );

        if ($saved) {
            $this->showForm = false;
            $this->resetForm();
        }
    }

    public function confirmDelete(int $unitId): void
    {
        $unit = UnitOfMeasure::findOrFail($unitId);
        $this->authorize('delete', $unit);

        $this->deletingId = $unit->id;
        $this->deletingName = $unit->name;
        $this->showDeleteModal = true;
    }

    public function delete(CatalogService $service): void
    {
        $unit = UnitOfMeasure::findOrFail($this->deletingId);
        $this->authorize('delete', $unit);

        if ($this->runDomainAction(fn () => $service->deleteUnit($unit), 'Unit deleted.')) {
            $this->showDeleteModal = false;
            $this->deletingId = null;
            $this->deletingName = '';
            $this->resetPage();
        }
    }

    public function render()
    {
        $units = UnitOfMeasure::query()
            ->withCount(['items', 'itemUnits'])
            ->search($this->search)
            ->when($this->typeFilter !== '', fn ($query) => $query->where('type', $this->typeFilter))
            ->orderBy('name')
            ->paginate(15);

        return view('livewire.catalog.units.index', [
            'units' => $units,
            'typeOptions' => UnitOfMeasureType::cases(),
        ]);
    }
}
