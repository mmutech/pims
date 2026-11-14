<?php

use App\Domain\Organization\Models\BusinessUnit;
use App\Domain\Warehouse\Models\Warehouse;
use App\Domain\Warehouse\Models\WarehouseLocation;
use App\Domain\Warehouse\Services\WarehouseService;
use App\DTOs\Warehouse\AccessGrantData;
use App\Enums\WarehouseAccessLevel;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed([PermissionSeeder::class, RoleSeeder::class]);
});

it('requires an explicit grant for non global users to view a warehouse', function () {
    $businessUnit = BusinessUnit::factory()->create();
    $warehouse = Warehouse::factory()->forBusinessUnit($businessUnit)->create();

    $officer = User::factory()
        ->withRole('Store Officer')
        ->create();

    expect($officer->can('view', $warehouse))->toBeFalse()
        ->and($officer->hasWarehouseAccess($warehouse))->toBeFalse();
});

it('grants access at the requested level', function () {
    $businessUnit = BusinessUnit::factory()->create();
    $warehouse = Warehouse::factory()->forBusinessUnit($businessUnit)->create();
    $actor = User::factory()->superAdmin()->create();

    $manager = User::factory()
        ->withRole('Warehouse Manager')
        ->create();

    app(WarehouseService::class)->grantAccess(
        $warehouse,
        new AccessGrantData($manager->id, WarehouseAccessLevel::Manage, true),
        $actor,
    );

    expect($manager->hasWarehouseAccess($warehouse))->toBeTrue()
        ->and($manager->hasWarehouseAccess($warehouse, WarehouseAccessLevel::Manage))->toBeTrue()
        ->and($manager->can('view', $warehouse))->toBeTrue()
        ->and($manager->can('update', $warehouse))->toBeTrue()
        ->and($manager->can('delete', $warehouse))->toBeFalse();
});

it('only gives global users view level access without an explicit grant', function () {
    $warehouse = Warehouse::factory()->create();
    $executive = User::factory()->withRole('Executive')->create();

    expect($executive->can('view', $warehouse))->toBeTrue()
        ->and($executive->hasWarehouseAccess($warehouse, WarehouseAccessLevel::Operate))->toBeFalse()
        ->and($executive->can('update', $warehouse))->toBeFalse();
});

it('revokes access without deleting the audit record', function () {
    $businessUnit = BusinessUnit::factory()->create();
    $warehouse = Warehouse::factory()->forBusinessUnit($businessUnit)->create();
    $actor = User::factory()->superAdmin()->create();
    $officer = User::factory()->withRole('Store Officer')->create();
    $service = app(WarehouseService::class);

    $service->grantAccess($warehouse, new AccessGrantData($officer->id, WarehouseAccessLevel::Operate), $actor);
    $service->revokeAccess($warehouse, $officer, $actor);

    expect($officer->fresh()->activeWarehouseIds())->toBeEmpty()
        ->and($warehouse->accessGrants()->where('user_id', $officer->id)->count())->toBe(1)
        ->and($warehouse->accessGrants()->where('user_id', $officer->id)->value('revoked_at'))->not->toBeNull();
});

it('refuses to grant warehouse access to an inactive user', function () {
    $warehouse = Warehouse::factory()->create();
    $actor = User::factory()->superAdmin()->create();
    $inactive = User::factory()->inactive()->create();

    expect(fn () => app(WarehouseService::class)->grantAccess(
        $warehouse,
        new AccessGrantData($inactive->id, WarehouseAccessLevel::View),
        $actor,
    ))->toThrow(DomainException::class, 'Warehouse access cannot be granted to an inactive user.');
});

it('prevents deleting a warehouse that still has locations', function () {
    $warehouse = Warehouse::factory()->create();
    WarehouseLocation::factory()->forWarehouse($warehouse)->create();

    expect(fn () => app(WarehouseService::class)->delete($warehouse))
        ->toThrow(DomainException::class, 'Warehouse cannot be deleted while locations exist.');
});
