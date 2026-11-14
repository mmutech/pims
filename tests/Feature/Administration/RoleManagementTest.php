<?php

use App\Domain\Administration\Services\RoleAdministrationService;
use App\Models\User;
use App\Support\Permissions;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed([PermissionSeeder::class, RoleSeeder::class]);
});

it('creates a custom role with selected permissions', function () {
    $role = app(RoleAdministrationService::class)->create('Regional Store Supervisor', [
        Permissions::ITEMS_VIEW,
        Permissions::WAREHOUSES_VIEW,
    ]);

    expect($role->name)->toBe('Regional Store Supervisor')
        ->and($role->permissions()->pluck('name')->all())
        ->toContain(Permissions::ITEMS_VIEW, Permissions::WAREHOUSES_VIEW);
});

it('renames a custom role and replaces its permissions', function () {
    $service = app(RoleAdministrationService::class);
    $role = $service->create('Temporary Role', [Permissions::ITEMS_VIEW]);

    $updated = $service->update($role, 'Renamed Role', [Permissions::UNITS_VIEW]);

    expect($updated->name)->toBe('Renamed Role')
        ->and($updated->permissions()->pluck('name')->all())->toBe([Permissions::UNITS_VIEW]);
});

it('locks system roles against modification', function () {
    $service = app(RoleAdministrationService::class);
    $role = Role::findByName('Auditor', 'web');

    expect(fn () => $service->update($role, 'Auditor Renamed', []))
        ->toThrow(DomainException::class, 'System roles cannot be changed through the application.');

    expect(fn () => $service->delete($role))
        ->toThrow(DomainException::class, 'System roles cannot be changed through the application.');
});

it('does not allow a reserved system role name for a custom role', function () {
    expect(fn () => app(RoleAdministrationService::class)->create('Auditor', []))
        ->toThrow(DomainException::class, 'That role name is reserved for a system role.');
});

it('does not delete a role that is assigned to users', function () {
    $service = app(RoleAdministrationService::class);
    $role = $service->create('Assigned Role', [Permissions::ITEMS_VIEW]);

    $user = User::factory()->create();
    $user->assignRole($role);

    expect(fn () => $service->delete($role))
        ->toThrow(DomainException::class, 'This role is assigned to users and cannot be deleted.');
});

it('ignores permissions that do not exist', function () {
    $service = app(RoleAdministrationService::class);
    $role = $service->create('Filtered Role', [Permissions::ITEMS_VIEW, 'not.a.real.permission']);

    expect($role->permissions()->pluck('name')->all())->toBe([Permissions::ITEMS_VIEW]);
});
