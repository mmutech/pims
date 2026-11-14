<?php

use App\Domain\Organization\Models\BusinessUnit;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed([PermissionSeeder::class, RoleSeeder::class]);
});

it('shows every business unit to users with the view permission', function () {
    $homeUnit = BusinessUnit::factory()->create();
    $otherUnit = BusinessUnit::factory()->create();

    $user = User::factory()->withRole('Store Officer')->create();

    expect($user->can('view', $homeUnit))->toBeTrue()
        ->and($user->can('view', $otherUnit))->toBeTrue();
});

it('gives global users access to every business unit', function () {
    $businessUnit = BusinessUnit::factory()->create();
    $user = User::factory()->withRole('Executive')->create();

    expect($user->hasGlobalAccess())->toBeTrue()
        ->and($user->can('view', $businessUnit))->toBeTrue();
});

it('enforces organization permissions through policies', function () {
    $businessUnit = BusinessUnit::factory()->create();

    $storeOfficer = User::factory()->withRole('Store Officer')->create();
    $executive = User::factory()->withRole('Executive')->create();

    expect($storeOfficer->can('viewAny', BusinessUnit::class))->toBeTrue()
        ->and($storeOfficer->can('create', BusinessUnit::class))->toBeFalse()
        ->and($executive->can('viewAny', BusinessUnit::class))->toBeTrue()
        ->and($executive->can('create', BusinessUnit::class))->toBeFalse();

    $this->actingAs($storeOfficer);
    expect($storeOfficer->can('update', $businessUnit))->toBeFalse();
});

it('denies access to users without the view permission', function () {
    $businessUnit = BusinessUnit::factory()->create();
    $user = User::factory()->create();

    expect($user->can('viewAny', BusinessUnit::class))->toBeFalse()
        ->and($user->can('view', $businessUnit))->toBeFalse();
});
