<?php

use App\Domain\Inventory\Models\StockTransaction;
use App\Domain\Stocktaking\Services\StockAdjustmentService;
use App\Enums\AdjustmentStatus;
use App\Enums\AdjustmentType;
use App\Enums\StockMovementType;

it('posts an increase adjustment below the approval threshold and updates the ledger', function () {
    $context = $this->makeStocktakingContext(stock: '100.0000', unitCost: '100.0000');

    $adjustment = $this->makeStockAdjustment($context, '5.0000', autoSubmit: true);

    expect($adjustment->status)->toBe(AdjustmentStatus::Approved)
        ->and($adjustment->document_number)->toStartWith('ADJ')
        ->and((string) $adjustment->total_value)->toBe('500.00');

    $posted = app(StockAdjustmentService::class)->post($adjustment, $context['warehouse_manager']);

    $balance = $this->inventoryBalanceFor($context);

    expect($posted->status)->toBe(AdjustmentStatus::Posted)
        ->and($posted->posted_by)->toBe($context['warehouse_manager']->id)
        ->and((string) $balance->on_hand_quantity)->toBe('105.0000')
        ->and($posted->transaction)->not->toBeNull()
        ->and($posted->transaction->transaction_type)->toBe(StockMovementType::AdjustmentIn)
        ->and($posted->transaction->items)->toHaveCount(1);
});

it('requires approval at or above the configured threshold before posting', function () {
    $context = $this->makeStocktakingContext(stock: '100.0000', unitCost: '100.0000');

    $adjustment = $this->makeStockAdjustment($context, '20.0000', autoSubmit: true);

    expect($adjustment->status)->toBe(AdjustmentStatus::PendingApproval)
        ->and($adjustment->requiresApproval())->toBeTrue()
        ->and($adjustment->approval_request_id)->not->toBeNull();

    expect(fn () => app(StockAdjustmentService::class)->post($adjustment, $context['warehouse_manager']))
        ->toThrow(DomainException::class);

    $this->approveDocument($adjustment, $context['warehouse_manager']);

    $adjustment->refresh();

    expect($adjustment->status)->toBe(AdjustmentStatus::Approved)
        ->and($adjustment->approved_at)->not->toBeNull();

    $posted = app(StockAdjustmentService::class)->post($adjustment, $context['warehouse_manager']);

    expect($posted->status)->toBe(AdjustmentStatus::Posted)
        ->and((string) $this->inventoryBalanceFor($context)->on_hand_quantity)->toBe('120.0000');
});

it('prevents the creator from posting their own adjustment', function () {
    $context = $this->makeStocktakingContext(stock: '100.0000', unitCost: '100.0000');
    $adjustment = $this->makeStockAdjustment($context, '5.0000', autoSubmit: true);

    expect(fn () => app(StockAdjustmentService::class)->post($adjustment, $context['store_officer']))
        ->toThrow(DomainException::class, 'The adjustment creator cannot post their own adjustment.');

    expect($adjustment->refresh()->status)->toBe(AdjustmentStatus::Approved)
        ->and((string) $this->inventoryBalanceFor($context)->on_hand_quantity)->toBe('100.0000');
});

it('rejects a decrease adjustment that would drive stock negative', function () {
    config(['inventory.adjustment_approval_threshold' => '0']);

    $context = $this->makeStocktakingContext(stock: '10.0000', unitCost: '100.0000');
    $adjustment = $this->makeStockAdjustment($context, '-20.0000', autoSubmit: true);

    expect($adjustment->adjustment_type)->toBe(AdjustmentType::Decrease);

    expect(fn () => app(StockAdjustmentService::class)->post($adjustment, $context['warehouse_manager']))
        ->toThrow(DomainException::class, 'Insufficient available stock');

    expect($adjustment->refresh()->status)->toBe(AdjustmentStatus::Approved)
        ->and((string) $this->inventoryBalanceFor($context)->on_hand_quantity)->toBe('10.0000');
});

it('reverses a posted adjustment with an equal and opposite document', function () {
    $context = $this->makeStocktakingContext(stock: '100.0000', unitCost: '100.0000');
    $adjustment = $this->makeStockAdjustment($context, '5.0000', autoSubmit: true);
    $posted = app(StockAdjustmentService::class)->post($adjustment, $context['warehouse_manager']);

    $reversal = app(StockAdjustmentService::class)->reverse(
        $posted,
        $context['controller'],
        'Wrong bin was adjusted.',
    );

    expect($posted->refresh()->status)->toBe(AdjustmentStatus::Reversed)
        ->and($reversal->status)->toBe(AdjustmentStatus::Posted)
        ->and($reversal->reversal_of_id)->toBe($posted->id)
        ->and($reversal->adjustment_type)->toBe(AdjustmentType::Decrease)
        ->and((string) $this->inventoryBalanceFor($context)->on_hand_quantity)->toBe('100.0000')
        ->and(StockTransaction::query()->where('source_type', $posted->getMorphClass())->count())->toBeGreaterThanOrEqual(2);
});

it('prevents the original poster from reversing their own adjustment', function () {
    $context = $this->makeStocktakingContext(stock: '100.0000', unitCost: '100.0000');
    $adjustment = $this->makeStockAdjustment($context, '5.0000', autoSubmit: true);
    $posted = app(StockAdjustmentService::class)->post($adjustment, $context['warehouse_manager']);

    expect(fn () => app(StockAdjustmentService::class)->reverse(
        $posted,
        $context['warehouse_manager'],
        'Attempted self reversal.',
    ))->toThrow(DomainException::class, 'The original poster cannot reverse their own adjustment.');

    expect($posted->refresh()->status)->toBe(AdjustmentStatus::Posted);
});

it('cancels a draft adjustment without touching stock', function () {
    $context = $this->makeStocktakingContext(stock: '100.0000', unitCost: '100.0000');
    $adjustment = $this->makeStockAdjustment($context, '5.0000');

    expect($adjustment->status)->toBe(AdjustmentStatus::Draft);

    $cancelled = app(StockAdjustmentService::class)->cancel(
        $adjustment,
        $context['store_officer'],
        'Raised in error.',
    );

    expect($cancelled->status)->toBe(AdjustmentStatus::Cancelled)
        ->and($cancelled->cancelled_at)->not->toBeNull()
        ->and((string) $this->inventoryBalanceFor($context)->on_hand_quantity)->toBe('100.0000');
});
