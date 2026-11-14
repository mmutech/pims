<?php

use App\Domain\Inventory\Models\InventoryBalance;
use App\Domain\Procurement\Models\PurchaseOrder;
use App\Domain\Reporting\Services\AuditReportService;
use App\Domain\Reporting\Services\InventoryReportService;
use App\Domain\Reporting\Services\ProcurementReportService;
use App\Domain\Reporting\Services\ProjectReportService;
use App\Domain\Reporting\Services\WarehouseReportService;
use App\Domain\Supplier\Models\Supplier;
use App\DTOs\Reporting\ReportCriteria;
use App\Models\User;

function reportingWarehouseContext(array $context): array
{
    $destination = $context;
    $destination['warehouse'] = $context['destination_warehouse'];
    $destination['dimension'] = $context['destination_dimension'];
    $destination['actor'] = $context['destination_officer'];

    return $destination;
}

it('reconciles valuation totals to the posted ledger', function () {
    $context = $this->reportingContext();

    $inventory = app(InventoryReportService::class);
    $criteria = new ReportCriteria;

    $valuation = $inventory->valuationSummary(null, $criteria);
    $movements = $inventory->movementsSummary(null, $criteria);
    $reconciliation = $inventory->reconciliationSummary(null, $criteria);

    expect($valuation['On hand'])->toBe('100')
        ->and($valuation['Value'])->toBe('10,000.00')
        ->and($movements['Qty in'])->toBe('100')
        ->and($movements['Net qty'])->toBe('100')
        ->and($movements['Net value'])->toBe('10,000.00')
        ->and($reconciliation['Warehouse/item pairs'])->toBe('1')
        ->and($reconciliation['Mismatches'])->toBe('0')
        ->and($reconciliation['Status'])->toBe('Reconciled');

    $rows = $inventory->reconciliation(null, $criteria);

    expect($rows->total())->toBe(1);

    $row = $rows->items()[0];

    expect((string) $row->balance_quantity)->toBe('100.0000')
        ->and((string) $row->ledger_quantity)->toBe('100.0000')
        ->and((string) $row->difference_quantity)->toBe('0.0000')
        ->and((string) $row->difference_value)->toBe('0.00');

    $movementRows = $inventory->movements(null, $criteria);

    expect($movementRows->total())->toBe(1)
        ->and($movementRows->items()[0]->movement_label)->toBe('Opening Balance');
});

it('flags a reconciliation mismatch when a balance drifts from the ledger', function () {
    $context = $this->reportingContext();

    InventoryBalance::query()
        ->where('warehouse_id', $context['warehouse']->id)
        ->update(['on_hand_quantity' => '95.0000', 'total_value' => '9500.00']);

    $inventory = app(InventoryReportService::class);

    $summary = $inventory->reconciliationSummary(null, new ReportCriteria);
    expect($summary['Mismatches'])->toBe('1')
        ->and($summary['Status'])->toBe('Attention required');

    $mismatches = $inventory->reconciliation(null, new ReportCriteria(mismatchesOnly: true));
    expect($mismatches->total())->toBe(1)
        ->and((string) $mismatches->items()[0]->difference_quantity)->toBe('-5.0000')
        ->and((string) $mismatches->items()[0]->difference_value)->toBe('-500.00');
});

it('limits warehouse reports to the warehouses a user can access', function () {
    $context = $this->reportingContext();
    $this->receiveStock(reportingWarehouseContext($context), '50.0000', '100.0000');

    $inventory = app(InventoryReportService::class);
    $warehouses = app(WarehouseReportService::class);

    $scoped = $inventory->valuationSummary($context['store_officer'], new ReportCriteria);
    $global = $inventory->valuationSummary($context['auditor'], new ReportCriteria);

    expect($scoped['On hand'])->toBe('100')
        ->and($scoped['Value'])->toBe('10,000.00')
        ->and($global['On hand'])->toBe('150')
        ->and($global['Value'])->toBe('15,000.00');

    expect($inventory->valuation($context['store_officer'], new ReportCriteria)->total())->toBe(1)
        ->and($inventory->valuation($context['auditor'], new ReportCriteria)->total())->toBe(2);

    expect($warehouses->utilization($context['store_officer'], new ReportCriteria)->total())->toBe(1)
        ->and($warehouses->utilization($context['auditor'], new ReportCriteria)->total())->toBe(2);

    $blocked = User::factory()
        ->withRole('Store Officer')
        ->create();

    expect($inventory->valuationSummary($blocked, new ReportCriteria)['On hand'])->toBe('0')
        ->and($inventory->valuation($blocked, new ReportCriteria)->total())->toBe(0)
        ->and($warehouses->utilization($blocked, new ReportCriteria)->total())->toBe(0);
});

it('derives warehouse throughput from posted movements', function () {
    $context = $this->reportingContext();

    $throughput = app(WarehouseReportService::class);

    $summary = $throughput->throughputSummary($context['store_officer'], new ReportCriteria);

    expect($summary['Qty in'])->toBe('100')
        ->and($summary['Qty out'])->toBe('0')
        ->and($summary['Net qty'])->toBe('100')
        ->and($summary['Net value'])->toBe('10,000.00');

    $rows = $throughput->throughput($context['store_officer'], new ReportCriteria);

    expect($rows->total())->toBe(1)
        ->and((string) $rows->items()[0]->inbound_quantity)->toBe('100.0000')
        ->and((string) $rows->items()[0]->net_value)->toBe('10000.00');
});

it('reports project consumption only from posted issues', function () {
    $context = $this->reportingContext();
    $project = $this->makeProject($context);

    $project->members()->create([
        'user_id' => $context['requester']->id,
        'role' => 'member',
        'joined_at' => now(),
        'is_active' => true,
    ]);

    $this->postedProjectIssue($context, $project, '10.0000');

    $projects = app(ProjectReportService::class);

    $summary = $projects->consumptionSummary($context['requester'], new ReportCriteria);
    expect($summary['Projects'])->toBe('1')
        ->and($summary['Issued qty'])->toBe('10')
        ->and($summary['Issued value'])->toBe('1,000.00');

    $rows = $projects->consumption($context['requester'], new ReportCriteria);
    expect($rows->total())->toBe(1)
        ->and($rows->items()[0]->project_code)->toBe($project->code)
        ->and((string) $rows->items()[0]->quantity)->toBe('10.0000')
        ->and((string) $rows->items()[0]->total_value)->toBe('1000.00');

    $outsider = User::factory()
        ->withRole('Department Requester')
        ->create();

    expect($projects->consumption($outsider, new ReportCriteria)->total())->toBe(0)
        ->and($projects->consumptionSummary($outsider, new ReportCriteria)['Projects'])->toBe('0');

    expect($projects->consumption($context['requester'], new ReportCriteria(projectId: $project->id))->total())->toBe(1);
});

it('respects procurement row visibility and supplier filters', function () {
    $context = $this->reportingContext();

    $supplier = Supplier::factory()->create();
    $otherSupplier = Supplier::factory()->create();

    PurchaseOrder::factory()->create([
        'supplier_id' => $supplier->id,
        'delivery_warehouse_id' => $context['warehouse']->id,
        'created_by' => $context['procurement_manager']->id,
        'total_amount' => '5000.00',
        'subtotal' => '5000.00',
    ]);

    PurchaseOrder::factory()->create([
        'supplier_id' => $otherSupplier->id,
        'delivery_warehouse_id' => $context['warehouse']->id,
        'created_by' => $context['procurement_manager']->id,
        'total_amount' => '2500.00',
        'subtotal' => '2500.00',
    ]);

    $otherManager = User::factory()
        ->withRole('Procurement Manager')
        ->create();

    PurchaseOrder::factory()->create([
        'supplier_id' => Supplier::factory()->create()->id,
        'delivery_warehouse_id' => $context['destination_warehouse']->id,
        'created_by' => $otherManager->id,
        'total_amount' => '9000.00',
        'subtotal' => '9000.00',
    ]);

    $procurement = app(ProcurementReportService::class);

    $ownSummary = $procurement->ordersSummary($context['procurement_manager'], new ReportCriteria);
    $globalSummary = $procurement->ordersSummary($context['auditor'], new ReportCriteria);

    expect($ownSummary['Orders'])->toBe('2')
        ->and($ownSummary['Committed value'])->toBe('7,500.00')
        ->and($globalSummary['Orders'])->toBe('3')
        ->and($globalSummary['Committed value'])->toBe('16,500.00');

    expect($procurement->orders($otherManager, new ReportCriteria)->total())->toBe(1)
        ->and($procurement->orders($context['procurement_manager'], new ReportCriteria)->total())->toBe(2)
        ->and($procurement->orders($context['auditor'], new ReportCriteria)->total())->toBe(3);

    $orders = $procurement->orders($context['procurement_manager'], new ReportCriteria);
    expect($orders->items()[0]->status_label)->toBe('Draft');

    $filtered = $procurement->orders($context['procurement_manager'], new ReportCriteria(supplierId: $supplier->id));
    expect($filtered->total())->toBe(1);

    $spend = $procurement->supplierSpendSummary($context['procurement_manager'], new ReportCriteria);
    expect($spend['Suppliers'])->toBe('2')
        ->and($spend['Committed value'])->toBe('7,500.00');
});

it('surfaces slow moving stock and the audit activity trail', function () {
    $context = $this->reportingContext();
    $inventory = app(InventoryReportService::class);

    $slow = $inventory->slowMoving($context['auditor'], new ReportCriteria(movementWindowDays: 90));
    expect($slow->total())->toBe(1)
        ->and($inventory->slowMovingSummary($context['auditor'], new ReportCriteria)['Items flagged'])->toBe('1');

    $this->issueStock($context, '5.0000');

    expect($inventory->slowMoving($context['auditor'], new ReportCriteria(movementWindowDays: 90))->total())->toBe(0);

    $this->receiveStock($context, '5.0000', '100.0000');

    activity('inventory')
        ->causedBy($context['store_officer'])
        ->event('created')
        ->log('Reporting audit test entry');

    $audit = app(AuditReportService::class);

    $summary = $audit->activitySummary($context['auditor'], new ReportCriteria);
    expect((int) $summary['Events'])->toBeGreaterThanOrEqual(1);

    $rows = $audit->activity($context['auditor'], new ReportCriteria(logName: 'inventory'));
    expect($rows->total())->toBe(1)
        ->and($rows->items()[0]->description)->toBe('Reporting audit test entry');

    $this->submitAndApproveRequisition(
        $this->makeMaterialRequisition($context, '1.0000'),
        $context,
    );

    $approvals = $audit->approvals($context['auditor'], new ReportCriteria);

    expect($approvals->total())->toBeGreaterThanOrEqual(1)
        ->and($approvals->items()[0]->status_label)->not->toBe('');
});
