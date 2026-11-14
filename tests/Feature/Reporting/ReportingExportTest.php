<?php

use App\Domain\Reporting\Services\ReportExportService;
use App\Domain\Reporting\Support\ReportCatalogue;
use App\Domain\Reporting\Support\ReportRows;
use App\DTOs\Reporting\ReportCriteria;

it('downloads a report as csv, xlsx and pdf for an authorised user', function (string $format, string $contentType) {
    $context = $this->reportingContext();

    $response = $this->actingAs($context['auditor'])->get(route('reports.export', [
        'report' => ReportCatalogue::INVENTORY_VALUATION,
        'format' => $format,
    ]));

    $response->assertOk();

    expect($response->headers->get('content-type'))->toContain($contentType)
        ->and($response->headers->get('content-disposition'))->toContain('attachment');
})->with([
    'csv' => ['csv', 'text/csv'],
    'xlsx' => ['xlsx', 'spreadsheetml'],
    'pdf' => ['pdf', 'application/pdf'],
]);

it('denies exports to users without the matching export permission', function () {
    $context = $this->reportingContext();

    $this->actingAs($context['requester_viewer'])
        ->get(route('reports.export', ['report' => ReportCatalogue::INVENTORY_VALUATION, 'format' => 'csv']))
        ->assertForbidden();

    $this->actingAs($context['requester_viewer'])
        ->get(route('reports.export', ['report' => ReportCatalogue::PROJECT_CONSUMPTION, 'format' => 'pdf']))
        ->assertForbidden();

    $this->actingAs($context['procurement_manager'])
        ->get(route('reports.export', ['report' => ReportCatalogue::INVENTORY_VALUATION, 'format' => 'csv']))
        ->assertForbidden();

    $this->actingAs($context['procurement_manager'])
        ->get(route('reports.export', ['report' => ReportCatalogue::PROCUREMENT_ORDERS, 'format' => 'csv']))
        ->assertOk();
});

it('returns not found for an unknown report key', function () {
    $context = $this->reportingContext();

    $this->actingAs($context['auditor'])
        ->get(route('reports.export', ['report' => 'inventory.unknown', 'format' => 'csv']))
        ->assertNotFound();
});

it('exports the same scoped rows the screen shows', function () {
    $context = $this->reportingContext();
    $this->receiveStock([
        ...$context,
        'warehouse' => $context['destination_warehouse'],
        'dimension' => $context['destination_dimension'],
        'actor' => $context['destination_officer'],
    ], '50.0000', '100.0000');

    $exports = app(ReportExportService::class);

    $scoped = $exports->payload(
        ReportCatalogue::INVENTORY_VALUATION,
        $context['store_officer'],
        new ReportCriteria,
    );

    $global = $exports->payload(
        ReportCatalogue::INVENTORY_VALUATION,
        $context['auditor'],
        new ReportCriteria,
    );

    expect($scoped['rows'])->toHaveCount(1)
        ->and($scoped['rows'][0]['quantity'])->toBe('100.0000')
        ->and($scoped['rows'][0]['total_value'])->toBe('10000.00')
        ->and($scoped['summary']['Value'])->toBe('10,000.00')
        ->and($global['rows'])->toHaveCount(2);

    expect($exports->filename(ReportCatalogue::INVENTORY_VALUATION, 'csv'))
        ->toStartWith('inventory-valuation-')
        ->toEndWith('.csv');
});

it('maps decimal columns to Excel column letters', function () {
    $definition = ReportCatalogue::find(ReportCatalogue::INVENTORY_VALUATION);
    $formats = ReportRows::numberFormats($definition['columns']);

    expect($formats)->toHaveKey('E')
        ->and($formats['E'])->toBe('#,##0.0000')
        ->and($formats['H'])->toBe('#,##0.00');

    $movement = ReportCatalogue::find(ReportCatalogue::INVENTORY_MOVEMENTS);
    $movementFormats = ReportRows::numberFormats($movement['columns']);

    expect($movementFormats)->toHaveKey('B')
        ->and($movementFormats['B'])->toBe('#,##0');
});
