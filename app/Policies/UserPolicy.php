<?php

namespace App\Policies;

use App\Models\User;
use App\Support\Permissions;

class UserPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isSuperAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::ADMIN_USERS_VIEW);
    }

    public function view(User $user, User $model): bool
    {
        return $user->can(Permissions::ADMIN_USERS_VIEW);
    }

    public function create(User $user): bool
    {
        return $user->can(Permissions::ADMIN_USERS_CREATE);
    }

    public function update(User $user, User $model): bool
    {
        return $user->can(Permissions::ADMIN_USERS_UPDATE);
    }

    public function delete(User $user, User $model): bool
    {
        return $user->can(Permissions::ADMIN_USERS_DELETE) && ! $user->is($model);
    }

    public function assignRoles(User $user, User $model): bool
    {
        return $user->can(Permissions::ADMIN_USERS_UPDATE);
    }

    public function manageWarehouseAccess(User $user, User $model): bool
    {
        return $user->can(Permissions::ADMIN_USERS_UPDATE);
    }
}
