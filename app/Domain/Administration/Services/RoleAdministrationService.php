<?php

namespace App\Domain\Administration\Services;

use DomainException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleAdministrationService
{
    /**
     * Roles that are provisioned by the system and cannot be renamed, deleted,
     * or have their permissions changed through the application.
     *
     * @var list<string>
     */
    public const SYSTEM_ROLES = ['Super Administrator', 'System Administrator', 'Auditor'];

    /**
     * @param  list<string>  $permissions
     */
    public function create(string $name, array $permissions): Role
    {
        $name = trim($name);

        if ($name === '') {
            throw new DomainException('The role name is required.');
        }

        if (in_array($name, self::SYSTEM_ROLES, true)) {
            throw new DomainException('That role name is reserved for a system role.');
        }

        if (Role::query()->where('name', $name)->where('guard_name', 'web')->exists()) {
            throw new DomainException('A role with that name already exists.');
        }

        $role = Role::create(['name' => $name, 'guard_name' => 'web']);
        $this->syncPermissions($role, $permissions);

        return $role->refresh();
    }

    /**
     * @param  list<string>  $permissions
     */
    public function update(Role $role, string $name, array $permissions): Role
    {
        $this->assertNotSystemRole($role);

        $name = trim($name);

        if ($name === '') {
            throw new DomainException('The role name is required.');
        }

        if (in_array($name, self::SYSTEM_ROLES, true)) {
            throw new DomainException('That role name is reserved for a system role.');
        }

        if (Role::query()
            ->where('name', $name)
            ->where('guard_name', 'web')
            ->whereKeyNot($role->id)
            ->exists()) {
            throw new DomainException('A role with that name already exists.');
        }

        $role->update(['name' => $name]);
        $this->syncPermissions($role, $permissions);

        return $role->refresh();
    }

    public function delete(Role $role): void
    {
        $this->assertNotSystemRole($role);

        if ($role->users()->exists()) {
            throw new DomainException('This role is assigned to users and cannot be deleted.');
        }

        $role->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * @param  list<string>  $permissions
     */
    public function syncPermissions(Role $role, array $permissions): void
    {
        $valid = Permission::query()
            ->where('guard_name', 'web')
            ->whereIn('name', array_values(array_unique($permissions)))
            ->pluck('name')
            ->all();

        $role->syncPermissions($valid);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function isSystemRole(Role $role): bool
    {
        return in_array($role->name, self::SYSTEM_ROLES, true);
    }

    private function assertNotSystemRole(Role $role): void
    {
        if ($this->isSystemRole($role)) {
            throw new DomainException('System roles cannot be changed through the application.');
        }
    }
}
