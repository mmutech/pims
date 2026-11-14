<?php

use App\Domain\Reporting\Support\ReportCatalogue;
use App\Support\Permissions;

it('defines a coherent catalogue entry for every report', function () {
    $all = ReportCatalogue::all();

    expect($all)->toHaveCount(12)
        ->and(array_keys($all))->toBe(ReportCatalogue::keys());

    $groups = ReportCatalogue::grouped();

    expect(array_keys($groups))->toBe(['Inventory', 'Procurement', 'Warehouse', 'Project', 'Audit'])
        ->and(array_keys($groups['Inventory']))->toBe([
            ReportCatalogue::INVENTORY_VALUATION,
            ReportCatalogue::INVENTORY_MOVEMENTS,
            ReportCatalogue::INVENTORY_SLOW_MOVING,
            ReportCatalogue::INVENTORY_EXPIRING,
            ReportCatalogue::INVENTORY_RECONCILIATION,
        ]);

    foreach ($all as $key => $definition) {
        expect($definition['key'])->toBe($key)
            ->and($definition['title'])->not->toBe('')
            ->and($definition['description'])->not->toBe('')
            ->and(Permissions::all())->toContain($definition['view'])
            ->and(Permissions::all())->toContain($definition['export'])
            ->and(class_exists($definition['service']))->toBeTrue()
            ->and(method_exists($definition['service'], $definition['method']))->toBeTrue()
            ->and(method_exists($definition['service'], $definition['summary']))->toBeTrue()
            ->and($definition['columns'])->not->toBeEmpty();
    }

    expect(ReportCatalogue::find('inventory.unknown'))->toBeNull();
});
