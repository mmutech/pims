<?php

namespace App\Domain\Administration\Services;

use App\DTOs\Administration\UserData;
use App\Enums\UserStatus;
use App\Enums\WarehouseAccessLevel;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class UserAdministrationService
{
    public function create(UserData $data, User $actor): User
    {
        return DB::transaction(function () use ($data, $actor): User {
            $attributes = $data->toArray();
            $attributes['password'] = $data->password ?: str()->password(16);

            $user = User::create($attributes);
            $this->syncRoles($user, $data->roles, $actor);
            $this->syncWarehouseAccess($user, $data->warehouseIds, $actor);

            return $user->refresh();
        });
    }

    public function update(User $user, UserData $data, User $actor): User
    {
        return DB::transaction(function () use ($user, $data, $actor): User {
            if (! $data->status->canLogin() && $user->is($actor)) {
                throw new DomainException('You cannot deactivate your own account.');
            }

            $user->update($data->toArray());
            $this->syncRoles($user, $data->roles, $actor);
            $this->syncWarehouseAccess($user, $data->warehouseIds, $actor);

            return $user->refresh();
        });
    }

    public function changeStatus(User $user, UserStatus $status, User $actor): User
    {
        if (! $status->canLogin() && $user->is($actor)) {
            throw new DomainException('You cannot deactivate your own account.');
        }

        $user->update(['status' => $status]);

        return $user->refresh();
    }

    public function deactivate(User $user, User $actor): void
    {
        if ($user->is($actor)) {
            throw new DomainException('You cannot deactivate your own account.');
        }

        $user->update(['status' => UserStatus::Inactive]);
        $user->delete();
    }

    /**
     * @param  list<string>  $roles
     */
    public function syncRoles(User $user, array $roles, User $actor): void
    {
        $roles = array_values(array_unique($roles));

        if ($user->is($actor) && $user->hasRole('Super Administrator') && ! in_array('Super Administrator', $roles, true)) {
            throw new DomainException('You cannot remove your own Super Administrator role.');
        }

        if (! $actor->isSuperAdmin()) {
            $systemRoles = Role::query()
                ->whereIn('name', ['Super Administrator', 'System Administrator', 'Auditor'])
                ->pluck('name')
                ->all();

            if (array_intersect($roles, $systemRoles) !== [] && ! $user->hasAnyRole($systemRoles)) {
                throw new DomainException('Only a Super Administrator can grant system roles.');
            }
        }

        $user->syncRoles($roles);
    }

    /**
     * @param  list<int>  $warehouseIds
     */
    public function syncWarehouseAccess(User $user, array $warehouseIds, User $actor): void
    {
        $warehouseIds = array_values(array_unique(array_map('intval', $warehouseIds)));
        $existing = $user->warehouseAccess()->pluck('warehouse_id')->all();

        foreach ($warehouseIds as $warehouseId) {
            $user->warehouseAccess()->updateOrCreate(
                ['warehouse_id' => $warehouseId],
                [
                    'access_level' => WarehouseAccessLevel::Operate,
                    'granted_by' => $actor->id,
                    'granted_at' => now(),
                    'revoked_at' => null,
                ],
            );
        }

        $toRevoke = array_diff($existing, $warehouseIds);

        if ($toRevoke !== []) {
            $user->warehouseAccess()
                ->whereIn('warehouse_id', $toRevoke)
                ->update(['revoked_at' => now()]);
        }
    }
}
