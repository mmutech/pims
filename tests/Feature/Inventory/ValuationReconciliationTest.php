<?php

use App\Domain\Inventory\Models\InventoryBalance;
use App\Domain\Inventory\Models\InventoryCostLayer;
use App\Domain\Inventory\Models\InventoryValuationSnapshot;
use App\Domain\Inventory\Models\StockLedger;
use App\Domain\Inventory\Services\InventoryReconciliationService;
use App\Domain\Inventory\Services\InventoryValuationService;
use App\Domain\Inventory\Services\StockReservationService;
use App\Enums\StockReservationStatus;
use App\Enums\ValuationMethod;
use App\Jobs\Inventory\ExpireStockReservations;
use App\Jobs\Inventory\GenerateInventoryValuationSnapshots;
use App\Jobs\Inventory\ReconcileInventoryBalances;
use Tests\Concerns\BuildsInventoryFixtures;

uses(BuildsInventoryFixtures::class);

function balanceFor(array $context): InventoryBalance
{
    return InventoryBalance::query()
        ->where('warehouse_id', $context['warehouse']->id)
        ->where('stock_dimension_id', $context['dimension']->id)
        ->where('item_id', $context['item']->id)
        ->where('batch_key', 0)
        ->firstOrFail();
}

it('writes a valuation snapshot per warehouse item and regenerates the same row', function () {
    $this->seedInventory();

    $context = $this->makeInventoryContext();
    $this->receiveStock($context, '10.0000', '100.0000');
    $this->receiveStock($context, '5.0000', '200.0000');

    $service = app(InventoryValuationService::class);

    expect($service->snapshot('2026-11-04'))->toBe(1);

    $snapshot = InventoryValuationSnapshot::query()->firstOrFail();

    expect($snapshot->quantity)->toBe('15.0000')
        ->and($snapshot->total_value)->toBe('2000.00')
        ->and($snapshot->unit_cost)->toBe('133.333333')
        ->and($snapshot->valuation_method)->toBe(ValuationMethod::WeightedAverage);

    $this->receiveStock($context, '5.0000', '100.0000');

    expect($service->snapshot('2026-11-04'))->toBe(1)
        ->and(InventoryValuationSnapshot::query()->count())->toBe(1);

    $snapshot->refresh();

    expect($snapshot->quantity)->toBe('20.0000')
        ->and($snapshot->total_value)->toBe('2500.00')
        ->and($snapshot->unit_cost)->toBe('125.000000');
});

it('stores snapshots separately for each warehouse and item', function () {
    $this->seedInventory();

    $first = $this->makeInventoryContext();
    $second = $this->makeInventoryContext();

    $this->receiveStock($first, '4.0000', '50.0000');
    $this->receiveStock($second, '7.0000', '25.0000');

    expect(app(InventoryValuationService::class)->snapshot('2026-11-04'))->toBe(2)
        ->and(InventoryValuationSnapshot::query()->count())->toBe(2);

    $firstRow = InventoryValuationSnapshot::query()
        ->where('warehouse_id', $first['warehouse']->id)
        ->where('item_id', $first['item']->id)
        ->firstOrFail();

    $secondRow = InventoryValuationSnapshot::query()
        ->where('warehouse_id', $second['warehouse']->id)
        ->where('item_id', $second['item']->id)
        ->firstOrFail();

    expect($firstRow->quantity)->toBe('4.0000')
        ->and($firstRow->total_value)->toBe('200.00')
        ->and($secondRow->quantity)->toBe('7.0000')
        ->and($secondRow->total_value)->toBe('175.00');
});

it('issues FIFO stock from the oldest cost layers first', function () {
    $this->seedInventory();

    $context = $this->makeInventoryContext();
    $context['item']->forceFill(['valuation_method' => ValuationMethod::Fifo])->save();

    $this->receiveStock($context, '5.0000', '100.0000');
    $this->receiveStock($context, '5.0000', '200.0000');

    $transaction = $this->issueStock($context, '6.0000');
    $line = $transaction->items()->firstOrFail();

    $balance = balanceFor($context);
    $layers = InventoryCostLayer::query()
        ->where('item_id', $context['item']->id)
        ->where('warehouse_id', $context['warehouse']->id)
        ->orderBy('sequence')
        ->get();

    $ledger = StockLedger::query()
        ->where('stock_transaction_id', $transaction->id)
        ->firstOrFail();

    expect($line->unit_cost)->toBe('116.666666')
        ->and($line->total_cost)->toBe('700.00')
        ->and($ledger->value_out)->toBe('700.00')
        ->and($balance->on_hand_quantity)->toBe('4.0000')
        ->and($balance->total_value)->toBe('800.00')
        ->and($balance->average_unit_cost)->toBe('200.000000')
        ->and($layers)->toHaveCount(2)
        ->and($layers[0]->remaining_quantity)->toBe('0.0000')
        ->and($layers[1]->remaining_quantity)->toBe('4.0000');
});

it('reconciles the ledger against balances and reservations', function () {
    $this->seedInventory();

    $context = $this->makeInventoryContext();
    $this->receiveStock($context, '10.0000', '100.0000');

    $reservation = $this->reserveStock($context, '3.0000');
    $line = $reservation->items()->firstOrFail();
    $this->issueStock($context, '2.0000', $line->id);

    $service = app(InventoryReconciliationService::class);
    $report = $service->reconcile();

    expect($service->isReconciled($report))->toBeTrue()
        ->and($report['balances'])->toBe(1)
        ->and($report['ledger_lines'])->toBe(2)
        ->and($report['quantity_discrepancies'])->toBe([])
        ->and($report['value_discrepancies'])->toBe([])
        ->and($report['reserved_discrepancies'])->toBe([]);

    $balance = balanceFor($context);
    $balance->forceFill([
        'on_hand_quantity' => '9.0000',
        'total_value' => '900.00',
    ])->save();

    $report = $service->reconcile();

    expect($service->isReconciled($report))->toBeFalse()
        ->and($report['quantity_discrepancies'])->toHaveCount(1)
        ->and($report['value_discrepancies'])->toHaveCount(1);

    expect(fn () => $service->assertReconciled())
        ->toThrow(DomainException::class, 'Inventory reconciliation failed');
});

it('detects reservation and balance projection drift', function () {
    $this->seedInventory();

    $context = $this->makeInventoryContext();
    $this->receiveStock($context, '10.0000');
    $this->reserveStock($context, '3.0000');

    $service = app(InventoryReconciliationService::class);

    expect($service->isReconciled($service->reconcile()))->toBeTrue();

    balanceFor($context)->forceFill([
        'reserved_quantity' => '0.0000',
        'available_quantity' => '10.0000',
    ])->save();

    $report = $service->reconcile();

    expect($service->isReconciled($report))->toBeFalse()
        ->and($report['reserved_discrepancies'])->toHaveCount(1)
        ->and($report['reserved_discrepancies'][0]['balance_reserved'])->toBe('0.0000')
        ->and($report['reserved_discrepancies'][0]['reservation_outstanding'])->toBe('3.0000');
});

it('generates valuation snapshots through the scheduled job', function () {
    $this->seedInventory();

    $context = $this->makeInventoryContext();
    $this->receiveStock($context, '6.0000', '25.0000');

    (new GenerateInventoryValuationSnapshots('2026-11-04', null, $context['actor']->id))
        ->handle(app(InventoryValuationService::class));

    $snapshot = InventoryValuationSnapshot::query()->firstOrFail();

    expect($snapshot->quantity)->toBe('6.0000')
        ->and($snapshot->total_value)->toBe('150.00')
        ->and($snapshot->created_by)->toBe($context['actor']->id);
});

it('expires reservations through the scheduled job', function () {
    $this->seedInventory();

    $context = $this->makeInventoryContext();
    $this->receiveStock($context, '8.0000');

    $reservation = $this->reserveStock($context, '2.0000', [
        'expires_at' => now()->subHour()->toDateTimeString(),
    ]);

    (new ExpireStockReservations)->handle(app(StockReservationService::class));

    expect($reservation->refresh()->status)->toBe(StockReservationStatus::Expired)
        ->and(balanceFor($context)->reserved_quantity)->toBe('0.0000');
});

it('fails the reconciliation job when projections drift', function () {
    $this->seedInventory();

    $context = $this->makeInventoryContext();
    $this->receiveStock($context, '5.0000');

    $job = new ReconcileInventoryBalances;

    $job->handle(app(InventoryReconciliationService::class));

    expect(StockLedger::query()->count())->toBe(1);

    balanceFor($context)->forceFill(['on_hand_quantity' => '4.0000'])->save();

    expect(fn () => $job->handle(app(InventoryReconciliationService::class)))
        ->toThrow(DomainException::class, 'Inventory reconciliation failed');
});
