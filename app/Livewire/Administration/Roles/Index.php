<?php

namespace App\Livewire\Administration\Roles;

use App\Domain\Administration\Services\RoleAdministrationService;
use App\Livewire\Concerns\HandlesDomainActions;
use App\Support\Permissions;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;

#[Layout('layouts.app')]
#[Title('Roles & Permissions')]
class Index extends Component
{
    use HandlesDomainActions, WithPagination;

    public string $search = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    /** @var array<string, mixed> */
    public array $form = [];

    public bool $showDeleteModal = false;

    public ?int $deletingId = null;

    public string $deletingName = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Role::class);
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
            'name' => '',
            'permissions' => [],
        ];
        $this->resetValidation();
    }

    public function openCreate(): void
    {
        $this->authorize('create', Role::class);
        $this->resetForm();
        $this->showForm = true;
    }

    public function openEdit(int $roleId): void
    {
        $role = Role::findOrFail($roleId);
        $this->authorize('update', $role);

        $this->editingId = $role->id;
        $this->form = [
            'name' => $role->name,
            'permissions' => $role->permissions()->pluck('name')->all(),
        ];
        $this->resetValidation();
        $this->showForm = true;
    }

    public function save(RoleAdministrationService $service): void
    {
        $role = $this->editingId !== null ? Role::findOrFail($this->editingId) : null;

        $role !== null
            ? $this->authorize('update', $role)
            : $this->authorize('create', Role::class);

        $this->validate([
            'form.name' => [
                'required', 'string', 'max:100',
                Rule::unique('roles', 'name')->where('guard_name', 'web')->ignore($role?->id),
            ],
            'form.permissions' => ['array'],
            'form.permissions.*' => ['string', Rule::exists('permissions', 'name')->where('guard_name', 'web')],
        ]);

        $saved = $this->runDomainAction(
            fn () => $role !== null
                ? $service->update($role, $this->form['name'], array_values($this->form['permissions']))
                : $service->create($this->form['name'], array_values($this->form['permissions'])),
            $role !== null ? 'Role updated.' : 'Role created.',
        );

        if ($saved) {
            $this->showForm = false;
            $this->resetForm();
        }
    }

    public function confirmDelete(int $roleId): void
    {
        $role = Role::findOrFail($roleId);
        $this->authorize('delete', $role);

        $this->deletingId = $role->id;
        $this->deletingName = $role->name;
        $this->showDeleteModal = true;
    }

    public function delete(RoleAdministrationService $service): void
    {
        $role = Role::findOrFail($this->deletingId);
        $this->authorize('delete', $role);

        if ($this->runDomainAction(fn () => $service->delete($role), 'Role deleted.')) {
            $this->showDeleteModal = false;
            $this->deletingId = null;
            $this->deletingName = '';
            $this->resetPage();
        }
    }

    public function render()
    {
        return view('livewire.administration.roles.index', [
            'roles' => Role::query()
                ->withCount(['permissions', 'users'])
                ->when($this->search !== '', fn ($query) => $query->where('name', 'like', '%'.$this->search.'%'))
                ->orderBy('name')
                ->paginate(15),
            'permissionGroups' => Permissions::groups(),
            'systemRoles' => RoleAdministrationService::SYSTEM_ROLES,
        ]);
    }
}
