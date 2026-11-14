<?php

namespace App\Livewire\Organization\CostCenters;

use App\Domain\Organization\Models\CostCenter;
use App\Domain\Organization\Models\Department;
use App\Domain\Organization\Services\OrganizationService;
use App\DTOs\Organization\CostCenterData;
use App\Livewire\Concerns\HandlesDomainActions;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Cost Centres')]
class Index extends Component
{
    use HandlesDomainActions, WithPagination;

    public string $search = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    /** @var array{department_id: string|int, code: string, name: string, description: string, is_active: bool} */
    public array $form = [];

    public bool $showDeleteModal = false;

    public ?int $deletingId = null;

    public string $deletingName = '';

    public function mount(): void
    {
        $this->authorize('viewAny', CostCenter::class);
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
            'department_id' => '',
            'code' => '',
            'name' => '',
            'description' => '',
            'is_active' => true,
        ];
        $this->resetValidation();
    }

    public function openCreate(): void
    {
        $this->authorize('create', CostCenter::class);
        $this->resetForm();
        $this->showForm = true;
    }

    public function openEdit(int $costCenterId): void
    {
        $costCenter = CostCenter::findOrFail($costCenterId);
        $this->authorize('update', $costCenter);

        $this->editingId = $costCenter->id;
        $this->form = [
            'department_id' => $costCenter->department_id !== null ? (string) $costCenter->department_id : '',
            'code' => $costCenter->code,
            'name' => $costCenter->name,
            'description' => (string) $costCenter->description,
            'is_active' => $costCenter->is_active,
        ];
        $this->resetValidation();
        $this->showForm = true;
    }

    public function save(OrganizationService $service): void
    {
        $costCenter = $this->editingId !== null ? CostCenter::findOrFail($this->editingId) : null;

        $costCenter !== null
            ? $this->authorize('update', $costCenter)
            : $this->authorize('create', CostCenter::class);

        $this->validate([
            'form.department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')],
            'form.code' => ['required', 'string', 'max:20', Rule::unique('cost_centers', 'code')->ignore($costCenter?->id)],
            'form.name' => ['required', 'string', 'max:150'],
            'form.description' => ['nullable', 'string', 'max:2000'],
            'form.is_active' => ['boolean'],
        ]);

        if (($this->form['department_id'] ?? '') === '') {
            $this->form['department_id'] = null;
        }

        $data = CostCenterData::fromArray($this->form);

        $saved = $this->runDomainAction(
            fn () => $costCenter !== null ? $service->updateCostCenter($costCenter, $data) : $service->createCostCenter($data),
            $costCenter !== null ? 'Cost centre updated.' : 'Cost centre created.',
        );

        if ($saved) {
            $this->showForm = false;
            $this->resetForm();
        }
    }

    public function confirmDelete(int $costCenterId): void
    {
        $costCenter = CostCenter::findOrFail($costCenterId);
        $this->authorize('delete', $costCenter);

        $this->deletingId = $costCenter->id;
        $this->deletingName = $costCenter->name;
        $this->showDeleteModal = true;
    }

    public function delete(OrganizationService $service): void
    {
        $costCenter = CostCenter::findOrFail($this->deletingId);
        $this->authorize('delete', $costCenter);

        if ($this->runDomainAction(fn () => $service->deleteCostCenter($costCenter), 'Cost centre deleted.')) {
            $this->showDeleteModal = false;
            $this->deletingId = null;
            $this->deletingName = '';
            $this->resetPage();
        }
    }

    public function render()
    {
        $costCenters = CostCenter::query()
            ->with('department')
            ->withCount('projects')
            ->search($this->search)
            ->orderBy('name')
            ->paginate(15);

        return view('livewire.organization.cost-centers.index', [
            'costCenters' => $costCenters,
            'departments' => Department::query()->orderBy('name')->get(),
        ]);
    }
}
