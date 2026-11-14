<?php

use App\Domain\Administration\Services\UserAdministrationService;
use App\Domain\Organization\Models\BusinessUnit;
use App\Domain\Warehouse\Models\Warehouse;
use App\DTOs\Administration\UserData;
use App\Enums\UserStatus;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed([PermissionSeeder::class, RoleSeeder::class]);
});

it('creates a user with roles and warehouse access', function () {
    $businessUnit = BusinessUnit::factory()->create();
    $warehouse = Warehouse::factory()->forBusinessUnit($businessUnit)->create();
    $actor = User::factory()->superAdmin()->create();

    $data = UserData::fromArray([
        'name' => 'New Store Officer',
        'email' => 'new.officer@disco.test',
        'employee_number' => 'EMP-9001',
        'status' => UserStatus::Active->value,
        'roles' => ['Store Officer'],
        'warehouse_ids' => [$warehouse->id],
    ]);

    $user = app(UserAdministrationService::class)->create($data, $actor);

    expect($user->hasRole('Store Officer'))->toBeTrue()
        ->and($user->activeWarehouseIds())->toContain($warehouse->id);
});

it('prevents a user from deactivating their own account', function () {
    $actor = User::factory()->superAdmin()->create();

    expect(fn () => app(UserAdministrationService::class)
        ->changeStatus($actor, UserStatus::Inactive, $actor))
        ->toThrow(DomainException::class, 'You cannot deactivate your own account.');
});

it('prevents a user from deactivating themselves through an update', function () {
    $actor = User::factory()->superAdmin()->create();

    $data = UserData::fromArray([
        'name' => $actor->name,
        'email' => $actor->email,
        'status' => UserStatus::Inactive->value,
    ]);

    expect(fn () => app(UserAdministrationService::class)->update($actor, $data, $actor))
        ->toThrow(DomainException::class, 'You cannot deactivate your own account.');
});

it('prevents a non super administrator from granting a system role', function () {
    $actor = User::factory()->withRole('System Administrator')->create();
    $target = User::factory()->create();

    expect(fn () => app(UserAdministrationService::class)->syncRoles($target, ['Auditor'], $actor))
        ->toThrow(DomainException::class, 'Only a Super Administrator can grant system roles.');
});

it('allows a non super administrator to grant an ordinary role', function () {
    $actor = User::factory()->withRole('System Administrator')->create();
    $target = User::factory()->create();

    app(UserAdministrationService::class)->syncRoles($target, ['Store Officer'], $actor);

    expect($target->fresh()->hasRole('Store Officer'))->toBeTrue();
});

it('revokes warehouse access when a user is removed from the assignment list', function () {
    $actor = User::factory()->superAdmin()->create();
    $user = User::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $service = app(UserAdministrationService::class);

    $service->syncWarehouseAccess($user, [$warehouse->id], $actor);
    expect($user->activeWarehouseIds())->toContain($warehouse->id);

    $service->syncWarehouseAccess($user, [], $actor);
    expect($user->fresh()->activeWarehouseIds())->toBeEmpty();
});

it('treats system administrators as global users', function () {
    $user = User::factory()->withRole('System Administrator')->create();

    expect($user->hasGlobalAccess())->toBeTrue();
});
