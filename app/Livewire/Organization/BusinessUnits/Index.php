<?php

namespace App\Livewire\Organization\BusinessUnits;

use App\Domain\Organization\Models\BusinessUnit;
use App\Domain\Organization\Services\OrganizationService;
use App\DTOs\Organization\BusinessUnitData;
use App\Livewire\Concerns\HandlesDomainActions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Business Units')]
class Index extends Component
{
    use HandlesDomainActions, WithPagination;

    public string $search = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    /** @var array{code: string, name: string, description: string, is_active: bool} */
    public array $form = [];

    public bool $showDeleteModal = false;

    public ?int $deletingId = null;

    public string $deletingName = '';

    public function mount(): void
    {
        $this->authorize('viewAny', BusinessUnit::class);
        $this->resetForm();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function resetForm(): void
    {
        $this->editingId = null;
        $this->form = [
            'code' => '',
            'name' => '',
            'description' => '',
            'is_active' => true,
        ];
        $this->resetValidation();
    }

    public function openCreate(): void
    {
        $this->authorize('create', BusinessUnit::class);
        $this->resetForm();
        $this->showForm = true;
    }

    public function openEdit(int $businessUnitId): void
    {
        $businessUnit = BusinessUnit::findOrFail($businessUnitId);
        $this->authorize('update', $businessUnit);

        $this->editingId = $businessUnit->id;
        $this->form = [
            'code' => $businessUnit->code,
            'name' => $businessUnit->name,
            'description' => (string) $businessUnit->description,
            'is_active' => $businessUnit->is_active,
        ];
        $this->resetValidation();
        $this->showForm = true;
    }

    public function save(OrganizationService $service): void
    {
        $businessUnit = $this->editingId !== null ? BusinessUnit::findOrFail($this->editingId) : null;

        $businessUnit !== null
            ? $this->authorize('update', $businessUnit)
            : $this->authorize('create', BusinessUnit::class);

        $this->validate([
            'form.code' => ['required', 'string', 'max:20', Rule::unique('business_units', 'code')->ignore($businessUnit?->id)],
            'form.name' => ['required', 'string', 'max:150'],
            'form.description' => ['nullable', 'string', 'max:2000'],
            'form.is_active' => ['boolean'],
        ]);

        $data = BusinessUnitData::fromArray($this->form);

        $saved = $this->runDomainAction(
            fn () => $businessUnit !== null ? $service->updateBusinessUnit($businessUnit, $data) : $service->createBusinessUnit($data),
            $businessUnit !== null ? 'Business unit updated.' : 'Business unit created.',
        );

        if ($saved) {
            $this->showForm = false;
            $this->resetForm();
        }
    }

    public function confirmDelete(int $businessUnitId): void
    {
        $businessUnit = BusinessUnit::findOrFail($businessUnitId);
        $this->authorize('delete', $businessUnit);

        $this->deletingId = $businessUnit->id;
        $this->deletingName = $businessUnit->name;
        $this->showDeleteModal = true;
    }

    public function delete(OrganizationService $service): void
    {
        $businessUnit = BusinessUnit::findOrFail($this->deletingId);
        $this->authorize('delete', $businessUnit);

        if ($this->runDomainAction(fn () => $service->deleteBusinessUnit($businessUnit), 'Business unit deleted.')) {
            $this->showDeleteModal = false;
            $this->deletingId = null;
            $this->deletingName = '';
            $this->resetPage();
        }
    }

    public function render()
    {
        $businessUnits = BusinessUnit::query()
            ->withCount('warehouses')
            ->search($this->search)
            ->orderBy('name')
            ->paginate(15);

        return view('livewire.organization.business-units.index', [
            'businessUnits' => $businessUnits,
        ]);
    }

    private function scopedQuery(Builder $query): Builder
    {
        return $query;
    }
}
