<?php

namespace App\Policies;

use App\Models\User;
use App\Support\Permissions;
use Spatie\Permission\Models\Role;

class RolePolicy
{
    private const SYSTEM_ROLES = ['Super Administrator', 'System Administrator', 'Auditor'];

    public function before(User $user, string $ability): ?bool
    {
        return $user->isSuperAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::ADMIN_ROLES_VIEW);
    }

    public function view(User $user, Role $role): bool
    {
        return $user->can(Permissions::ADMIN_ROLES_VIEW);
    }

    public function create(User $user): bool
    {
        return $user->can(Permissions::ADMIN_ROLES_CREATE);
    }

    public function update(User $user, Role $role): bool
    {
        return $user->can(Permissions::ADMIN_ROLES_UPDATE) && ! $this->isSystemRole($role);
    }

    public function delete(User $user, Role $role): bool
    {
        return $user->can(Permissions::ADMIN_ROLES_DELETE) && ! $this->isSystemRole($role);
    }

    private function isSystemRole(Role $role): bool
    {
        return in_array($role->name, self::SYSTEM_ROLES, true);
    }
}
