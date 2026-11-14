<?php

use App\Domain\Stocktaking\Models\StockCount;
use App\Domain\Stocktaking\Services\StockAdjustmentService;
use App\Domain\Stocktaking\Services\StockCountService;
use App\Domain\Stocktaking\Services\StockReconciliationService;
use App\DTOs\Stocktaking\StockCountEntryData;
use App\Enums\AdjustmentStatus;
use App\Enums\AdjustmentType;
use App\Enums\CountStatus;
use App\Enums\CountType;
use App\Enums\VarianceResolution;

it('captures a snapshot and tracks post-snapshot movements as the expected quantity', function () {
    $context = $this->makeStocktakingContext(stock: '100.0000', unitCost: '100.0000');
    $service = app(StockCountService::class);

    $count = $service->start($this->makeStockCount($context), $context['store_officer']);
    $item = $count->items()->firstOrFail();

    expect($count->status)->toBe(CountStatus::Counting)
        ->and($count->document_number)->toStartWith('CNT')
        ->and($count->snapshot_ledger_id)->not->toBeNull()
        ->and((string) $item->system_quantity)->toBe('100.0000');

    $this->issueStock($context, '10.0000');

    expect($service->expectedQuantity($count->refresh(), $item->refresh()))->toBe('90.0000');

    $service->recordCount($count, [
        new StockCountEntryData(stockCountItemId: $item->id, countedQuantity: '88.0000'),
    ], $context['counter']);

    $item->refresh();
    $variance = $count->refresh()->variances()->firstOrFail();

    expect((string) $item->counted_quantity)->toBe('88.0000')
        ->and((string) $item->variance_quantity)->toBe('-2.0000')
        ->and($count->status)->toBe(CountStatus::VarianceReview)
        ->and($variance->variance_type->value)->toBe('loss')
        ->and((string) $variance->quantity)->toBe('-2.0000')
        ->and((string) $variance->value)->toBe('200.00')
        ->and($variance->tolerance_breached)->toBeTrue();
});

it('hides system quantities for blind counts', function () {
    $context = $this->makeStocktakingContext(stock: '100.0000');
    $count = $this->makeStockCount($context, CountType::Blind);

    expect($count->isBlind())->toBeTrue()
        ->and($count->hidesSystemQuantity())->toBeTrue();
});

it('records recounts and recalculates the variance', function () {
    $context = $this->makeStocktakingContext(stock: '100.0000', unitCost: '100.0000');
    $count = $this->countedStockCount($context, '95.0000');
    $item = $count->items()->firstOrFail();
    $service = app(StockCountService::class);

    expect((string) $item->variance_quantity)->toBe('-5.0000');

    $recounted = $service->recount($count, $item->id, '97.0000', $context['counter'], 'Second physical count.');

    expect($recounted->recounts)->toHaveCount(1)
        ->and((string) $recounted->recounts->first()->counted_quantity)->toBe('97.0000')
        ->and((string) $recounted->counted_quantity)->toBe('97.0000')
        ->and((string) $recounted->variance_quantity)->toBe('-3.0000')
        ->and($count->refresh()->status)->toBe(CountStatus::Recount);
});

it('creates adjustments from variances and completes the count after reconciliation', function () {
    $context = $this->makeStocktakingContext(stock: '100.0000', unitCost: '100.0000');
    $count = $this->countedStockCount($context, '98.0000');
    $service = app(StockCountService::class);
    $adjustments = app(StockAdjustmentService::class);

    $created = $service->createAdjustments($count->refresh(), $context['warehouse_manager']);

    expect($created)->toHaveCount(1)
        ->and($count->refresh()->status)->toBe(CountStatus::AdjustmentPending);

    $adjustment = $created->first();

    expect($adjustment->status)->toBe(AdjustmentStatus::Approved)
        ->and($adjustment->adjustment_type)->toBe(AdjustmentType::Decrease)
        ->and($adjustment->stock_count_id)->toBe($count->id)
        ->and($count->variances()->firstOrFail()->stock_adjustment_id)->toBe($adjustment->id);

    $adjustments->post($adjustment, $context['controller']);

    expect((string) $this->inventoryBalanceFor($context)->on_hand_quantity)->toBe('98.0000')
        ->and($count->variances()->firstOrFail()->resolution)->toBe(VarianceResolution::Adjusted);

    $reconciliations = app(StockReconciliationService::class);
    $reconciliation = $reconciliations->prepare($count->refresh(), $context['warehouse_manager']);

    expect($reconciliation->document_number)->toStartWith('REC')
        ->and((string) $reconciliation->snapshot_quantity)->toBe('100.0000')
        ->and((string) $reconciliation->movement_quantity)->toBe('0.0000')
        ->and((string) $reconciliation->adjustment_quantity)->toBe('-2.0000')
        ->and((string) $reconciliation->final_quantity)->toBe('98.0000')
        ->and((string) $reconciliation->difference_quantity)->toBe('0.0000');

    $reconciliations->approve($reconciliation, $context['controller']);
    $posted = $reconciliations->post($reconciliation->refresh(), $context['warehouse_manager']);

    expect($posted->status->value)->toBe('posted')
        ->and($posted->posted_at)->not->toBeNull();

    $completed = $service->complete($count->refresh(), $context['warehouse_manager']);

    expect($completed->status)->toBe(CountStatus::Completed)
        ->and($completed->completed_at)->not->toBeNull();
});

it('reconciles snapshot plus movements plus adjustments against the ledger', function () {
    $context = $this->makeStocktakingContext(stock: '100.0000', unitCost: '100.0000');
    $service = app(StockCountService::class);
    $count = $service->start($this->makeStockCount($context), $context['store_officer']);
    $item = $count->items()->firstOrFail();

    $this->issueStock($context, '15.0000');

    $service->recordCount($count, [
        new StockCountEntryData(stockCountItemId: $item->id, countedQuantity: '82.0000'),
    ], $context['counter']);

    $created = $service->createAdjustments($count->refresh(), $context['warehouse_manager']);
    app(StockAdjustmentService::class)->post($created->first(), $context['controller']);

    $values = app(StockReconciliationService::class)->reconcile($count->refresh());

    expect($values['snapshot_quantity'])->toBe('100.0000')
        ->and($values['movement_quantity'])->toBe('-15.0000')
        ->and($values['adjustment_quantity'])->toBe('-3.0000')
        ->and($values['final_quantity'])->toBe('82.0000')
        ->and($values['difference_quantity'])->toBe('0.0000');
});

it('prevents counters and the scheduler from adjusting count variances', function () {
    $context = $this->makeStocktakingContext(stock: '100.0000', unitCost: '100.0000');
    $count = $this->countedStockCount($context, '98.0000');
    $service = app(StockCountService::class);

    expect($context['counter']->can('adjust', $count))->toBeFalse();

    expect(fn () => $service->createAdjustments($count->refresh(), $context['counter']))
        ->toThrow(DomainException::class, 'A counter cannot adjust variances for items they counted.');

    expect(fn () => $service->createAdjustments($count->refresh(), $context['store_officer']))
        ->toThrow(DomainException::class, 'The user who scheduled a stock count cannot adjust its variances.');
});

it('blocks completing a count while adjustments remain open', function () {
    $context = $this->makeStocktakingContext(stock: '100.0000', unitCost: '100.0000');
    $count = $this->countedStockCount($context, '98.0000');
    $service = app(StockCountService::class);

    $service->createAdjustments($count->refresh(), $context['warehouse_manager']);

    expect(fn () => $service->complete($count->refresh(), $context['warehouse_manager']))
        ->toThrow(DomainException::class, 'All count adjustments must be posted before completion.');
});

it('prevents cancelling a count that has posted adjustments', function () {
    $context = $this->makeStocktakingContext(stock: '100.0000', unitCost: '100.0000');
    $count = $this->countedStockCount($context, '98.0000');
    $service = app(StockCountService::class);

    $created = $service->createAdjustments($count->refresh(), $context['warehouse_manager']);
    app(StockAdjustmentService::class)->post($created->first(), $context['controller']);

    expect(fn () => $service->cancel($count->refresh(), $context['warehouse_manager'], 'No longer needed.'))
        ->toThrow(DomainException::class, 'A count with posted adjustments cannot be cancelled. Reverse them first.');
});

it('scopes count snapshot lines to the warehouse and selected items', function () {
    $context = $this->makeStocktakingContext(stock: '40.0000');
    $service = app(StockCountService::class);
    $count = $this->makeStockCount($context);

    expect($count->scopes)->toHaveCount(1);

    $started = $service->start($count, $context['store_officer']);

    expect($started)->toBeInstanceOf(StockCount::class)
        ->and($started->items)->toHaveCount(1)
        ->and((string) $started->items->first()->system_quantity)->toBe('40.0000');
});
