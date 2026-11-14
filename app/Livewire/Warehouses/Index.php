<?php

namespace App\Livewire\Warehouses;

use App\Domain\Organization\Models\BusinessUnit;
use App\Domain\Warehouse\Models\Warehouse;
use App\Domain\Warehouse\Services\WarehouseService;
use App\DTOs\Warehouse\WarehouseData;
use App\Enums\WarehouseType;
use App\Livewire\Concerns\HandlesDomainActions;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Warehouses')]
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
        $this->authorize('viewAny', Warehouse::class);
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
            'business_unit_id' => '',
            'code' => '',
            'name' => '',
            'type' => WarehouseType::Central->value,
            'address' => '',
            'city' => '',
            'state' => '',
            'country' => 'Nigeria',
            'postal_code' => '',
            'phone' => '',
            'email' => '',
            'manager_id' => '',
            'is_active' => true,
        ];
        $this->resetValidation();
    }

    public function openCreate(): void
    {
        $this->authorize('create', Warehouse::class);
        $this->resetForm();
        $this->showForm = true;
    }

    public function openEdit(int $warehouseId): void
    {
        $warehouse = Warehouse::findOrFail($warehouseId);
        $this->authorize('update', $warehouse);

        $this->editingId = $warehouse->id;
        $this->form = [
            'business_unit_id' => (string) $warehouse->business_unit_id,
            'code' => $warehouse->code,
            'name' => $warehouse->name,
            'type' => $warehouse->type->value,
            'address' => (string) $warehouse->address,
            'city' => (string) $warehouse->city,
            'state' => (string) $warehouse->state,
            'country' => (string) $warehouse->country,
            'postal_code' => (string) $warehouse->postal_code,
            'phone' => (string) $warehouse->phone,
            'email' => (string) $warehouse->email,
            'manager_id' => $warehouse->manager_id !== null ? (string) $warehouse->manager_id : '',
            'is_active' => $warehouse->is_active,
        ];
        $this->resetValidation();
        $this->showForm = true;
    }

    public function save(WarehouseService $service): void
    {
        $warehouse = $this->editingId !== null ? Warehouse::findOrFail($this->editingId) : null;

        $warehouse !== null
            ? $this->authorize('update', $warehouse)
            : $this->authorize('create', Warehouse::class);

        $this->validate([
            'form.business_unit_id' => ['required', 'integer', Rule::exists('business_units', 'id')],
            'form.code' => ['required', 'string', 'max:30', Rule::unique('warehouses', 'code')->ignore($warehouse?->id)],
            'form.name' => ['required', 'string', 'max:150'],
            'form.type' => ['required', Rule::enum(WarehouseType::class)],
            'form.address' => ['nullable', 'string', 'max:1000'],
            'form.city' => ['nullable', 'string', 'max:100'],
            'form.state' => ['nullable', 'string', 'max:100'],
            'form.country' => ['nullable', 'string', 'max:100'],
            'form.postal_code' => ['nullable', 'string', 'max:20'],
            'form.phone' => ['nullable', 'string', 'max:30'],
            'form.email' => ['nullable', 'email', 'max:150'],
            'form.manager_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'form.is_active' => ['boolean'],
        ]);

        $payload = $this->form;
        $payload['manager_id'] = $this->form['manager_id'] !== '' ? (int) $this->form['manager_id'] : null;

        $data = WarehouseData::fromArray($payload);

        $saved = $this->runDomainAction(
            fn () => $warehouse !== null
                ? $service->update($warehouse, $data, auth()->user())
                : $service->create($data, auth()->user()),
            $warehouse !== null ? 'Warehouse updated.' : 'Warehouse created.',
        );

        if ($saved) {
            $this->showForm = false;
            $this->resetForm();
        }
    }

    public function confirmDelete(int $warehouseId): void
    {
        $warehouse = Warehouse::findOrFail($warehouseId);
        $this->authorize('delete', $warehouse);

        $this->deletingId = $warehouse->id;
        $this->deletingName = $warehouse->name;
        $this->showDeleteModal = true;
    }

    public function delete(WarehouseService $service): void
    {
        $warehouse = Warehouse::findOrFail($this->deletingId);
        $this->authorize('delete', $warehouse);

        if ($this->runDomainAction(fn () => $service->delete($warehouse), 'Warehouse deleted.')) {
            $this->showDeleteModal = false;
            $this->deletingId = null;
            $this->deletingName = '';
            $this->resetPage();
        }
    }

    public function render()
    {
        $user = auth()->user();

        $warehouses = $this->scopedQuery(Warehouse::query())
            ->with(['businessUnit', 'manager'])
            ->withCount(['locations', 'bins', 'accessGrants' => fn (Builder $query) => $query->whereNull('revoked_at')])
            ->search($this->search)
            ->when($this->typeFilter !== '', fn (Builder $query) => $query->where('type', $this->typeFilter))
            ->orderBy('name')
            ->paginate(15);

        return view('livewire.warehouses.index', [
            'warehouses' => $warehouses,
            'businessUnits' => $this->businessUnits(),
            'managers' => User::query()->where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'typeOptions' => WarehouseType::cases(),
        ]);
    }

    /**
     * @return Collection<int, BusinessUnit>
     */
    private function businessUnits()
    {
        return BusinessUnit::query()
            ->orderBy('name')
            ->get();
    }

    private function scopedQuery(Builder $query): Builder
    {
        $user = auth()->user();

        if ($user->hasGlobalAccess()) {
            return $query;
        }

        return $query->whereIn('id', $user->activeWarehouseIds());
    }
}
