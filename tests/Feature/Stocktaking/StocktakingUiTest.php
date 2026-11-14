<?php

use App\Enums\CountStatus;
use App\Livewire\Stocktaking\Counts\Show as CountShow;
use Livewire\Livewire;

it('renders the stocktaking index and create screens', function () {
    $context = $this->makeStocktakingContext(stock: '100.0000', unitCost: '100.0000');

    $this->actingAs($context['warehouse_manager']);

    $this->get(route('stocktaking.adjustments.index'))
        ->assertOk()
        ->assertSee('Stock Adjustments');

    $this->get(route('stocktaking.adjustments.create'))
        ->assertOk();

    $this->get(route('stocktaking.counts.index'))
        ->assertOk()
        ->assertSee('Stock Counts');

    $this->get(route('stocktaking.counts.create'))
        ->assertOk();

    $this->get(route('stocktaking.reconciliations.index'))
        ->assertOk()
        ->assertSee('Stock Reconciliations');
});

it('renders adjustment and count detail screens and starts a count from the UI', function () {
    $context = $this->makeStocktakingContext(stock: '100.0000', unitCost: '100.0000');

    $adjustment = $this->makeStockAdjustment($context, '5.0000');
    $count = $this->makeStockCount($context);

    $this->actingAs($context['warehouse_manager']);

    $this->get(route('stocktaking.adjustments.show', $adjustment))
        ->assertOk()
        ->assertSee($adjustment->document_number);

    $this->get(route('stocktaking.adjustments.edit', $adjustment))
        ->assertOk();

    $this->get(route('stocktaking.counts.show', $count))
        ->assertOk()
        ->assertSee($count->document_number);

    $this->get(route('stocktaking.counts.edit', $count))
        ->assertOk();

    Livewire::test(CountShow::class, ['count' => $count])
        ->call('start')
        ->assertHasNoErrors();

    expect($count->refresh()->status)->toBe(CountStatus::Counting)
        ->and($count->items()->count())->toBe(1);
});
