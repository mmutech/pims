<?php

use App\Domain\Stocktaking\Services\StockAdjustmentService;
use App\Domain\Stocktaking\Services\StockCountService;
use App\Domain\Stocktaking\Services\StockReconciliationService;
use App\Models\User;

it('scopes stocktaking documents to warehouses the user may access', function () {
    $context = $this->makeStocktakingContext(stock: '100.0000', unitCost: '100.0000');

    $adjustment = $this->makeStockAdjustment($context, '5.0000');
    $count = $this->makeStockCount($context);

    $outsider = User::factory()
        ->withRole('Store Officer')
        ->create();

    expect($outsider->can('view', $adjustment))->toBeFalse()
        ->and($outsider->can('view', $count))->toBeFalse()
        ->and($context['warehouse_manager']->can('view', $adjustment))->toBeTrue()
        ->and($context['warehouse_manager']->can('view', $count))->toBeTrue();
});

it('lets counters record but never adjust count variances', function () {
    $context = $this->makeStocktakingContext(stock: '100.0000', unitCost: '100.0000');
    $count = $this->countedStockCount($context, '98.0000');

    expect($context['counter']->can('count', $count))->toBeTrue()
        ->and($context['counter']->can('adjust', $count))->toBeFalse()
        ->and($context['store_officer']->can('adjust', $count))->toBeFalse()
        ->and($context['warehouse_manager']->can('adjust', $count))->toBeTrue();
});

it('prevents the adjustment creator from posting their own document', function () {
    $context = $this->makeStocktakingContext(stock: '100.0000', unitCost: '100.0000');

    $adjustment = $this->makeStockAdjustment(
        $context,
        '5.0000',
        actor: $context['warehouse_manager'],
        autoSubmit: true,
    );

    expect($context['warehouse_manager']->can('post', $adjustment))->toBeFalse()
        ->and($context['controller']->can('post', $adjustment))->toBeTrue()
        ->and($context['counter']->can('post', $adjustment))->toBeFalse();
});

it('prevents the reconciliation preparer from approving their own statement', function () {
    $context = $this->makeStocktakingContext(stock: '100.0000', unitCost: '100.0000');
    $count = $this->countedStockCount($context, '98.0000');

    $created = app(StockCountService::class)
        ->createAdjustments($count->refresh(), $context['warehouse_manager']);

    app(StockAdjustmentService::class)->post($created->first(), $context['controller']);

    $reconciliation = app(StockReconciliationService::class)
        ->prepare($count->refresh(), $context['warehouse_manager']);

    expect($context['warehouse_manager']->can('approve', $reconciliation))->toBeFalse()
        ->and($context['controller']->can('approve', $reconciliation))->toBeTrue()
        ->and($context['counter']->can('approve', $reconciliation))->toBeFalse();
});
