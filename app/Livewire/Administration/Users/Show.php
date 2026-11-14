<?php

namespace App\Livewire\Administration\Users;

use App\Domain\Administration\Services\UserAdministrationService;
use App\Domain\Warehouse\Models\Warehouse;
use App\Enums\UserStatus;
use App\Livewire\Concerns\HandlesDomainActions;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

#[Layout('layouts.app')]
#[Title('User')]
class Show extends Component
{
    use HandlesDomainActions;

    public User $user;

    /** @var list<string> */
    public array $roleForm = [];

    /** @var list<int> */
    public array $warehouseForm = [];

    public string $statusForm = '';

    public function mount(User $user): void
    {
        $this->user = $user;
        $this->authorize('view', $this->user);

        $this->resetForms();
    }

    public function resetForms(): void
    {
        $this->roleForm = $this->user->roles()->pluck('name')->all();
        $this->warehouseForm = $this->user->warehouseAccess()
            ->whereNull('revoked_at')
            ->pluck('warehouse_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
        $this->statusForm = $this->user->status->value;
        $this->resetValidation();
    }

    public function saveRoles(UserAdministrationService $service): void
    {
        $this->authorize('assignRoles', $this->user);

        $this->validate([
            'roleForm' => ['array'],
            'roleForm.*' => ['string', 'exists:roles,name'],
        ]);

        if ($this->runDomainAction(
            fn () => $service->syncRoles($this->user, array_values($this->roleForm), auth()->user()),
            'Roles updated.',
        )) {
            $this->user->refresh();
        }
    }

    public function saveWarehouseAccess(UserAdministrationService $service): void
    {
        $this->authorize('manageWarehouseAccess', $this->user);

        $this->validate([
            'warehouseForm' => ['array'],
            'warehouseForm.*' => ['integer', 'exists:warehouses,id'],
        ]);

        if (! auth()->user()->hasGlobalAccess()) {
            $allowed = auth()->user()->activeWarehouseIds();

            if (array_diff(array_map('intval', $this->warehouseForm), $allowed) !== []) {
                $this->error('You may only grant access to warehouses you are assigned to.');

                return;
            }
        }

        if ($this->runDomainAction(
            fn () => $service->syncWarehouseAccess($this->user, array_map('intval', $this->warehouseForm), auth()->user()),
            'Warehouse access updated.',
        )) {
            $this->user->refresh();
        }
    }

    public function changeStatus(UserAdministrationService $service): void
    {
        $this->authorize('update', $this->user);

        $this->validate([
            'statusForm' => ['required', 'string', 'in:active,suspended,inactive'],
        ]);

        $status = UserStatus::from($this->statusForm);

        if ($this->runDomainAction(
            fn () => $service->changeStatus($this->user, $status, auth()->user()),
            "User status changed to {$status->label()}.",
        )) {
            $this->user->refresh();
        }
    }

    public function render()
    {
        $this->user->refresh();

        $assignableRoles = Role::query()->orderBy('name')->get();

        if (! auth()->user()->isSuperAdmin()) {
            $assignableRoles = $assignableRoles->reject(
                fn (Role $role): bool => in_array($role->name, ['Super Administrator', 'System Administrator', 'Auditor'], true)
                    && ! $this->user->hasRole($role->name),
            );
        }

        $warehouses = Warehouse::query()
            ->when(! auth()->user()->hasGlobalAccess(), fn (Builder $query) => $query->whereIn('id', auth()->user()->activeWarehouseIds()))
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        return view('livewire.administration.users.show', [
            'assignableRoles' => $assignableRoles,
            'warehouses' => $warehouses,
            'statusOptions' => UserStatus::cases(),
            'grants' => $this->user->warehouseAccess()
                ->with(['warehouse', 'grantedBy'])
                ->whereNull('revoked_at')
                ->orderByDesc('granted_at')
                ->get(),
            'activity' => Activity::query()
                ->where('subject_type', $this->user->getMorphClass())
                ->where('subject_id', $this->user->id)
                ->with('causer')
                ->latest()
                ->limit(15)
                ->get(),
        ]);
    }
}
