<?php

use App\Domain\Catalog\Models\Item;
use App\Domain\Catalog\Models\ItemCategory;
use App\Domain\Catalog\Models\UnitOfMeasure;
use App\Domain\Catalog\Services\CatalogService;
use App\DTOs\Catalog\ItemData;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed([PermissionSeeder::class, RoleSeeder::class]);
});

it('creates an item with its base unit and specifications', function () {
    $category = ItemCategory::factory()->create();
    $unit = UnitOfMeasure::factory()->create();

    $data = ItemData::fromArray([
        'category_id' => $category->id,
        'code' => 'itm-1001',
        'name' => '11kV Insulator',
        'base_unit_of_measure_id' => $unit->id,
        'minimum_stock' => '10.0000',
        'maximum_stock' => '500.0000',
        'reorder_level' => '25.0000',
        'reorder_quantity' => '100.0000',
        'standard_cost' => '4500.5000',
        'specifications' => [
            ['attribute' => 'Voltage', 'value' => '11kV'],
            ['attribute' => 'Material', 'value' => 'Porcelain'],
        ],
    ]);

    $item = app(CatalogService::class)->createItem($data);

    expect($item->code)->toBe('ITM-1001')
        ->and($item->specifications()->count())->toBe(2)
        ->and($item->units()->where('is_base', true)->value('unit_of_measure_id'))->toBe($unit->id)
        ->and($item->standard_cost)->toBe('4500.5000');
});

it('prevents changing an item code once created', function () {
    $item = Item::factory()->create();
    $service = app(CatalogService::class);

    $data = ItemData::fromArray([
        'category_id' => $item->category_id,
        'code' => 'CHANGED-CODE',
        'name' => $item->name,
        'base_unit_of_measure_id' => $item->base_unit_of_measure_id,
    ]);

    expect(fn () => $service->updateItem($item, $data))
        ->toThrow(DomainException::class, 'The item code is immutable once created.');
});

it('rejects a negative standard cost', function () {
    $category = ItemCategory::factory()->create();
    $unit = UnitOfMeasure::factory()->create();

    $data = ItemData::fromArray([
        'category_id' => $category->id,
        'code' => 'ITM-2001',
        'name' => 'Negative Cost Item',
        'base_unit_of_measure_id' => $unit->id,
        'standard_cost' => '-1.0000',
    ]);

    expect(fn () => app(CatalogService::class)->createItem($data))
        ->toThrow(DomainException::class, 'Standard cost cannot be negative.');
});

it('rejects a maximum stock below the minimum stock', function () {
    $category = ItemCategory::factory()->create();
    $unit = UnitOfMeasure::factory()->create();

    $data = ItemData::fromArray([
        'category_id' => $category->id,
        'code' => 'ITM-2002',
        'name' => 'Bad Stock Range Item',
        'base_unit_of_measure_id' => $unit->id,
        'minimum_stock' => '50.0000',
        'maximum_stock' => '10.0000',
    ]);

    expect(fn () => app(CatalogService::class)->createItem($data))
        ->toThrow(DomainException::class, 'Maximum stock must be greater than or equal to minimum stock.');
});

it('rejects an unknown base unit of measure', function () {
    $category = ItemCategory::factory()->create();

    $data = ItemData::fromArray([
        'category_id' => $category->id,
        'code' => 'ITM-2003',
        'name' => 'Unknown Unit Item',
        'base_unit_of_measure_id' => 999999,
    ]);

    expect(fn () => app(CatalogService::class)->createItem($data))
        ->toThrow(DomainException::class, 'The selected base unit of measure does not exist.');
});

it('enforces catalog permissions through policies', function () {
    $item = Item::factory()->create();

    $storeOfficer = User::factory()->withRole('Store Officer')->create();
    $superAdmin = User::factory()->superAdmin()->create();

    expect($storeOfficer->can('viewAny', Item::class))->toBeTrue()
        ->and($storeOfficer->can('create', Item::class))->toBeFalse()
        ->and($storeOfficer->can('update', $item))->toBeFalse()
        ->and($superAdmin->can('create', Item::class))->toBeTrue()
        ->and($superAdmin->can('update', $item))->toBeTrue();
});
