<?php

use App\Domain\Reporting\Services\DashboardMetricsService;
use App\Domain\Reporting\Support\ReportCatalogue;
use App\Models\User;

it('renders the report centre for an auditor', function () {
    $context = $this->reportingContext();

    $this->actingAs($context['auditor'])
        ->get(route('reports.index'))
        ->assertOk()
        ->assertSee('Report Centre')
        ->assertSee('Inventory')
        ->assertSee('Procurement')
        ->assertSee('Warehouse')
        ->assertSee('Project')
        ->assertSee('Audit');
});

it('shows only the report groups a role can view', function () {
    $context = $this->reportingContext();

    $this->actingAs($context['requester_viewer'])
        ->get(route('reports.index'))
        ->assertOk()
        ->assertSee('Inventory')
        ->assertSee('Project')
        ->assertDontSee('Open audit reports')
        ->assertDontSee('Open procurement reports');
});

it('renders each report screen and switches reports by query string', function () {
    $context = $this->reportingContext();

    $this->actingAs($context['store_officer'])
        ->get(route('reports.inventory'))
        ->assertOk()
        ->assertSee('Inventory valuation');

    $this->actingAs($context['store_officer'])
        ->get(route('reports.inventory', ['report' => ReportCatalogue::INVENTORY_RECONCILIATION]))
        ->assertOk()
        ->assertSee('Ledger reconciliation')
        ->assertSee('Reconciled');

    $this->actingAs($context['auditor'])
        ->get(route('reports.warehouses', ['report' => ReportCatalogue::WAREHOUSE_THROUGHPUT]))
        ->assertOk()
        ->assertSee('Warehouse throughput');

    $this->actingAs($context['auditor'])
        ->get(route('reports.procurement'))
        ->assertOk()
        ->assertSee('Purchase order register');

    $this->actingAs($context['auditor'])
        ->get(route('reports.projects'))
        ->assertOk()
        ->assertSee('Project consumption');

    $this->actingAs($context['auditor'])
        ->get(route('reports.audit'))
        ->assertOk()
        ->assertSee('Activity audit trail');
});

it('forbids report screens for roles without the view permission', function () {
    $context = $this->reportingContext();

    $this->actingAs($context['requester_viewer'])
        ->get(route('reports.procurement'))
        ->assertForbidden();

    $this->actingAs($context['requester_viewer'])
        ->get(route('reports.warehouses'))
        ->assertForbidden();

    $this->actingAs($context['requester_viewer'])
        ->get(route('reports.audit'))
        ->assertForbidden();
});

it('falls back to the group default when the requested report belongs elsewhere', function () {
    $context = $this->reportingContext();

    $this->actingAs($context['auditor'])
        ->get(route('reports.inventory', ['report' => ReportCatalogue::AUDIT_ACTIVITY]))
        ->assertOk()
        ->assertSee('Inventory valuation')
        ->assertDontSee('Activity audit trail');
});

it('shows permission-aware dashboard KPI cards', function () {
    $context = $this->reportingContext();

    $this->actingAs($context['store_officer'])
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Key performance indicators')
        ->assertSee('Inventory value')
        ->assertSee('Open purchase orders');

    $this->actingAs($context['requester_viewer'])
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Inventory value')
        ->assertSee('Project consumption (MTD)')
        ->assertDontSee('Open purchase orders');
});

it('shows recent warehouse-scoped stock movements to inventory report viewers', function () {
    $context = $this->reportingContext();
    $service = app(DashboardMetricsService::class);

    $chart = $service->stockMovementChart($context['store_officer']);

    expect($chart)->not->toBeNull()
        ->and($chart['has_data'])->toBeTrue()
        ->and($chart['points'])->toHaveCount(6)
        ->and($chart['points'][5]['label'])->toBe(now()->format('M'))
        ->and($chart['points'][5]['inbound'])->toBe('100')
        ->and($chart['points'][5]['outbound'])->toBe('0');

    $this->actingAs($context['store_officer'])
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Stock movement')
        ->assertSee('data-chart="stock-movements"', false);

    expect($service->stockMovementChart(User::factory()->create()))->toBeNull();
});
