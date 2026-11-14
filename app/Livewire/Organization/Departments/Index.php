<?php

namespace App\Livewire\Organization\Departments;

use App\Domain\Organization\Models\Department;
use App\Domain\Organization\Services\OrganizationService;
use App\DTOs\Organization\DepartmentData;
use App\Livewire\Concerns\HandlesDomainActions;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Departments')]
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
        $this->authorize('viewAny', Department::class);
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
        $this->authorize('create', Department::class);
        $this->resetForm();
        $this->showForm = true;
    }

    public function openEdit(int $departmentId): void
    {
        $department = Department::findOrFail($departmentId);
        $this->authorize('update', $department);

        $this->editingId = $department->id;
        $this->form = [
            'code' => $department->code,
            'name' => $department->name,
            'description' => (string) $department->description,
            'is_active' => $department->is_active,
        ];
        $this->resetValidation();
        $this->showForm = true;
    }

    public function save(OrganizationService $service): void
    {
        $department = $this->editingId !== null ? Department::findOrFail($this->editingId) : null;

        $department !== null
            ? $this->authorize('update', $department)
            : $this->authorize('create', Department::class);

        $this->validate([
            'form.code' => ['required', 'string', 'max:20', Rule::unique('departments', 'code')->ignore($department?->id)],
            'form.name' => ['required', 'string', 'max:150'],
            'form.description' => ['nullable', 'string', 'max:2000'],
            'form.is_active' => ['boolean'],
        ]);

        $data = DepartmentData::fromArray($this->form);

        $saved = $this->runDomainAction(
            fn () => $department !== null ? $service->updateDepartment($department, $data) : $service->createDepartment($data),
            $department !== null ? 'Department updated.' : 'Department created.',
        );

        if ($saved) {
            $this->showForm = false;
            $this->resetForm();
        }
    }

    public function confirmDelete(int $departmentId): void
    {
        $department = Department::findOrFail($departmentId);
        $this->authorize('delete', $department);

        $this->deletingId = $department->id;
        $this->deletingName = $department->name;
        $this->showDeleteModal = true;
    }

    public function delete(OrganizationService $service): void
    {
        $department = Department::findOrFail($this->deletingId);
        $this->authorize('delete', $department);

        if ($this->runDomainAction(fn () => $service->deleteDepartment($department), 'Department deleted.')) {
            $this->showDeleteModal = false;
            $this->deletingId = null;
            $this->deletingName = '';
            $this->resetPage();
        }
    }

    public function render()
    {
        $departments = Department::query()
            ->withCount('costCenters')
            ->search($this->search)
            ->orderBy('name')
            ->paginate(15);

        return view('livewire.organization.departments.index', [
            'departments' => $departments,
        ]);
    }
}
