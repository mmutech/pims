<?php

namespace App\Livewire\Administration\Users;

use App\Domain\Administration\Services\UserAdministrationService;
use App\Domain\Organization\Models\CostCenter;
use App\Domain\Organization\Models\Department;
use App\Domain\Warehouse\Models\Warehouse;
use App\DTOs\Administration\UserData;
use App\Enums\UserStatus;
use App\Livewire\Concerns\HandlesDomainActions;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;

#[Layout('layouts.app')]
#[Title('Users')]
class Index extends Component
{
    use HandlesDomainActions, WithPagination;

    public string $search = '';

    public string $statusFilter = '';

    public string $roleFilter = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    /** @var array<string, mixed> */
    public array $form = [];

    public bool $showDeactivateModal = false;

    public ?int $deactivatingId = null;

    public string $deactivatingName = '';

    public function mount(): void
    {
        $this->authorize('viewAny', User::class);
        $this->resetForm();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedRoleFilter(): void
    {
        $this->resetPage();
    }

    public function resetForm(): void
    {
        $this->editingId = null;
        $this->form = [
            'name' => '',
            'email' => '',
            'password' => '',
            'employee_number' => '',
            'phone' => '',
            'job_title' => '',
            'department_id' => '',
            'cost_center_id' => '',
            'status' => UserStatus::Active->value,
            'roles' => [],
            'warehouse_ids' => [],
        ];
        $this->resetValidation();
    }

    public function openCreate(): void
    {
        $this->authorize('create', User::class);
        $this->resetForm();
        $this->showForm = true;
    }

    public function openEdit(int $userId): void
    {
        $user = User::findOrFail($userId);
        $this->authorize('update', $user);

        $this->editingId = $user->id;
        $this->form = [
            'name' => $user->name,
            'email' => $user->email,
            'password' => '',
            'employee_number' => (string) $user->employee_number,
            'phone' => (string) $user->phone,
            'job_title' => (string) $user->job_title,
            'department_id' => $user->department_id !== null ? (string) $user->department_id : '',
            'cost_center_id' => $user->cost_center_id !== null ? (string) $user->cost_center_id : '',
            'status' => $user->status->value,
            'roles' => $user->roles()->pluck('name')->all(),
            'warehouse_ids' => $user->warehouseAccess()
                ->whereNull('revoked_at')
                ->pluck('warehouse_id')
                ->map(fn ($id): int => (int) $id)
                ->all(),
        ];
        $this->resetValidation();
        $this->showForm = true;
    }

    public function save(UserAdministrationService $service): void
    {
        $user = $this->editingId !== null ? User::findOrFail($this->editingId) : null;

        $user !== null
            ? $this->authorize('update', $user)
            : $this->authorize('create', User::class);

        $this->validate([
            'form.name' => ['required', 'string', 'max:150'],
            'form.email' => [
                'required', 'string', 'email', 'max:150',
                Rule::unique('users', 'email')->ignore($user?->id),
            ],
            'form.password' => ['nullable', 'string', 'min:8', 'max:100'],
            'form.employee_number' => [
                'nullable', 'string', 'max:30',
                Rule::unique('users', 'employee_number')->ignore($user?->id),
            ],
            'form.phone' => ['nullable', 'string', 'max:30'],
            'form.job_title' => ['nullable', 'string', 'max:120'],
            'form.department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')],
            'form.cost_center_id' => ['nullable', 'integer', Rule::exists('cost_centers', 'id')],
            'form.status' => ['required', Rule::enum(UserStatus::class)],
            'form.roles' => ['array'],
            'form.roles.*' => ['string', Rule::exists('roles', 'name')->where('guard_name', 'web')],
            'form.warehouse_ids' => ['array'],
            'form.warehouse_ids.*' => ['integer', Rule::exists('warehouses', 'id')],
        ]);

        $warehouseIds = array_map('intval', $this->form['warehouse_ids']);

        if (! auth()->user()->hasGlobalAccess()) {
            $allowed = auth()->user()->activeWarehouseIds();

            if (array_diff($warehouseIds, $allowed) !== []) {
                $this->error('You may only grant access to warehouses you are assigned to.');

                return;
            }
        }

        $payload = $this->form;
        $payload['department_id'] = $this->form['department_id'] !== '' ? (int) $this->form['department_id'] : null;
        $payload['cost_center_id'] = $this->form['cost_center_id'] !== '' ? (int) $this->form['cost_center_id'] : null;
        $payload['roles'] = array_values($this->form['roles']);
        $payload['warehouse_ids'] = array_values($warehouseIds);

        $data = UserData::fromArray($payload);

        $saved = $this->runDomainAction(
            fn () => $user !== null
                ? $service->update($user, $data, auth()->user())
                : $service->create($data, auth()->user()),
            $user !== null ? 'User updated.' : 'User created.',
        );

        if ($saved) {
            $this->showForm = false;
            $this->resetForm();
        }
    }

    public function confirmDeactivate(int $userId): void
    {
        $user = User::findOrFail($userId);
        $this->authorize('delete', $user);

        $this->deactivatingId = $user->id;
        $this->deactivatingName = $user->name;
        $this->showDeactivateModal = true;
    }

    public function deactivate(UserAdministrationService $service): void
    {
        $user = User::findOrFail($this->deactivatingId);
        $this->authorize('delete', $user);

        if ($this->runDomainAction(fn () => $service->deactivate($user, auth()->user()), 'User deactivated.')) {
            $this->showDeactivateModal = false;
            $this->deactivatingId = null;
            $this->deactivatingName = '';
            $this->resetPage();
        }
    }

    public function render()
    {
        $users = $this->scopedQuery(User::query())
            ->with(['roles', 'department'])
            ->when($this->search !== '', function (Builder $query): void {
                $term = '%'.$this->search.'%';

                $query->where(function (Builder $query) use ($term): void {
                    $query->where('name', 'like', $term)
                        ->orWhere('email', 'like', $term)
                        ->orWhere('employee_number', 'like', $term)
                        ->orWhere('job_title', 'like', $term);
                });
            })
            ->when($this->statusFilter !== '', fn (Builder $query) => $query->where('status', $this->statusFilter))
            ->when($this->roleFilter !== '', fn (Builder $query) => $query->whereHas(
                'roles',
                fn (Builder $query) => $query->where('name', $this->roleFilter),
            ))
            ->orderBy('name')
            ->paginate(15);

        return view('livewire.administration.users.index', [
            'users' => $users,
            'departments' => Department::query()->orderBy('name')->get(),
            'costCenters' => CostCenter::query()->orderBy('name')->get(),
            'warehouses' => $this->warehouses(),
            'roles' => $this->assignableRoles(),
            'statusOptions' => UserStatus::cases(),
            'roleOptions' => Role::query()->orderBy('name')->pluck('name'),
        ]);
    }

    /**
     * @return Collection<int, Warehouse>
     */
    private function warehouses()
    {
        $user = auth()->user();

        return Warehouse::query()
            ->when(! $user->hasGlobalAccess(), fn (Builder $query) => $query->whereIn('id', $user->activeWarehouseIds()))
            ->orderBy('name')
            ->get(['id', 'code', 'name']);
    }

    /**
     * @return Collection<int, Role>
     */
    private function assignableRoles()
    {
        $roles = Role::query()->orderBy('name')->get();

        if (auth()->user()->isSuperAdmin()) {
            return $roles;
        }

        return $roles->reject(fn (Role $role): bool => in_array($role->name, ['Super Administrator', 'System Administrator', 'Auditor'], true));
    }

    /**
     * Non-global administrators only see themselves and users who share one of
     * their warehouses. Business units no longer scope user records.
     */
    private function scopedQuery(Builder $query): Builder
    {
        $user = auth()->user();

        if ($user->hasGlobalAccess()) {
            return $query;
        }

        $warehouseIds = $user->activeWarehouseIds();

        return $query->where(function (Builder $query) use ($user, $warehouseIds): void {
            $query->whereKey($user->id);

            if ($warehouseIds !== []) {
                $query->orWhereHas('warehouseAccess', function (Builder $query) use ($warehouseIds): void {
                    $query->whereNull('revoked_at')->whereIn('warehouse_id', $warehouseIds);
                });
            }
        });
    }
}
