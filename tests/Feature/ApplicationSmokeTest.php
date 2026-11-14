<?php

use App\Domain\Catalog\Models\Item;
use App\Domain\Supplier\Models\Supplier;
use App\Domain\Warehouse\Models\Warehouse;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed([PermissionSeeder::class, RoleSeeder::class]);
});

it('renders every phase one index screen for a super administrator', function () {
    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin);

    $routes = [
        'dashboard',
        'organization.business-units.index',
        'organization.departments.index',
        'organization.cost-centers.index',
        'organization.projects.index',
        'warehouses.index',
        'catalog.items.index',
        'catalog.categories.index',
        'catalog.units.index',
        'suppliers.index',
        'administration.users.index',
        'administration.roles.index',
        'administration.sequences.index',
        'administration.settings.index',
        'administration.activity.index',
    ];

    foreach ($routes as $route) {
        $this->get(route($route))->assertOk();
    }
});

it('renders the detail screens for a super administrator', function () {
    $admin = User::factory()->superAdmin()->create();
    $warehouse = Warehouse::factory()->create();
    $item = Item::factory()->create();
    $supplier = Supplier::factory()->create();

    $this->actingAs($admin);

    $this->get(route('warehouses.show', $warehouse))->assertOk();
    $this->get(route('catalog.items.show', $item))->assertOk();
    $this->get(route('suppliers.show', $supplier))->assertOk();
    $this->get(route('administration.users.show', $admin))->assertOk();
});

it('renders the activity log for an auditor', function () {
    $auditor = User::factory()->withRole('Auditor')->create();

    $this->actingAs($auditor);

    $this->get(route('administration.activity.index'))->assertOk();
});

it('forbids access to administration screens without permission', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $this->get(route('administration.users.index'))->assertForbidden();
    $this->get(route('administration.roles.index'))->assertForbidden();
    $this->get(route('administration.sequences.index'))->assertForbidden();
    $this->get(route('administration.settings.index'))->assertForbidden();
    $this->get(route('administration.activity.index'))->assertForbidden();
});
