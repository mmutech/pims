<?php

use App\Domain\Inventory\Models\InventoryBalance;
use App\Domain\Inventory\Models\StockDimension;
use App\Domain\Inventory\Models\StockLedger;
use App\Domain\Inventory\Services\StockReservationService;
use App\Enums\StockDimensionType;
use App\Enums\StockReservationStatus;
use Spatie\Activitylog\Models\Activity;
use Tests\Concerns\BuildsInventoryFixtures;

uses(BuildsInventoryFixtures::class);

function inventoryBalance(array $context): InventoryBalance
{
    return InventoryBalance::query()
        ->where('warehouse_id', $context['warehouse']->id)
        ->where('stock_dimension_id', $context['dimension']->id)
        ->where('item_id', $context['item']->id)
        ->where('batch_key', 0)
        ->firstOrFail();
}

it('reserves available stock without moving on-hand quantities', function () {
    $this->seedInventory();

    $context = $this->makeInventoryContext();
    $this->receiveStock($context, '10.0000');

    $reservation = $this->reserveStock($context, '4.0000');
    $reservation->load('items');

    $balance = inventoryBalance($context);

    expect($reservation->status)->toBe(StockReservationStatus::Active)
        ->and($reservation->document_number)->toStartWith('RES/')
        ->and($reservation->items)->toHaveCount(1)
        ->and($reservation->items->first()->quantity)->toBe('4.0000')
        ->and($balance->on_hand_quantity)->toBe('10.0000')
        ->and($balance->reserved_quantity)->toBe('4.0000')
        ->and($balance->available_quantity)->toBe('6.0000')
        ->and(StockLedger::query()->count())->toBe(1);
});

it('rejects reservations above available stock and leaves balances unchanged', function () {
    $this->seedInventory();

    $context = $this->makeInventoryContext();
    $this->receiveStock($context, '10.0000');

    $this->reserveStock($context, '6.0000');

    expect(fn () => $this->reserveStock($context, '5.0000', ['source' => $context['warehouse']]))
        ->toThrow(DomainException::class, 'Insufficient available stock');

    $balance = inventoryBalance($context);

    expect($balance->reserved_quantity)->toBe('6.0000')
        ->and($balance->available_quantity)->toBe('4.0000');
});

it('allows only one open reservation per source document', function () {
    $this->seedInventory();

    $context = $this->makeInventoryContext();
    $this->receiveStock($context, '10.0000');

    $reservation = $this->reserveStock($context, '2.0000');

    expect(fn () => $this->reserveStock($context, '1.0000'))
        ->toThrow(DomainException::class, 'An open stock reservation already exists');

    app(StockReservationService::class)->release($reservation, 'No longer required', [], $context['actor']);

    $replacement = $this->reserveStock($context, '1.0000');

    expect($replacement->id)->not->toBe($reservation->id)
        ->and(inventoryBalance($context)->reserved_quantity)->toBe('1.0000');

    expect(fn () => app(StockReservationService::class)->release($reservation->refresh(), 'Again', [], $context['actor']))
        ->toThrow(DomainException::class, 'Only open stock reservations');
});

it('partially releases quantities and restores availability', function () {
    $this->seedInventory();

    $context = $this->makeInventoryContext();
    $this->receiveStock($context, '10.0000');

    $reservation = $this->reserveStock($context, '10.0000');
    $line = $reservation->items()->firstOrFail();

    $service = app(StockReservationService::class);
    $reservation = $service->release($reservation, 'Partial cancellation', [$line->id => '4.0000'], $context['actor']);
    $line->refresh();

    expect($line->released_quantity)->toBe('4.0000')
        ->and($line->outstandingQuantity())->toBe('6.0000')
        ->and($reservation->status)->toBe(StockReservationStatus::Active)
        ->and(inventoryBalance($context)->reserved_quantity)->toBe('6.0000')
        ->and(inventoryBalance($context)->available_quantity)->toBe('4.0000');

    $reservation = $service->release($reservation->refresh(), 'No longer required', [], $context['actor']);

    expect($reservation->status)->toBe(StockReservationStatus::Released)
        ->and($reservation->release_reason)->toBe('No longer required')
        ->and($reservation->closed_at)->not->toBeNull()
        ->and(inventoryBalance($context)->reserved_quantity)->toBe('0.0000')
        ->and(inventoryBalance($context)->available_quantity)->toBe('10.0000')
        ->and(Activity::query()->where('log_name', 'inventory')->count())->toBe(2);
});

it('requires a release reason and rejects releases above the outstanding quantity', function () {
    $this->seedInventory();

    $context = $this->makeInventoryContext();
    $this->receiveStock($context, '10.0000');

    $reservation = $this->reserveStock($context, '5.0000');
    $line = $reservation->items()->firstOrFail();
    $service = app(StockReservationService::class);

    expect(fn () => $service->release($reservation, '   ', [], $context['actor']))
        ->toThrow(DomainException::class, 'release reason is required');

    expect(fn () => $service->release($reservation, 'Too much', [$line->id => '6.0000'], $context['actor']))
        ->toThrow(DomainException::class, 'only 5.0000 is outstanding');

    expect(inventoryBalance($context)->reserved_quantity)->toBe('5.0000');
});

it('expires open reservations and releases outstanding stock', function () {
    $this->seedInventory();

    $context = $this->makeInventoryContext();
    $this->receiveStock($context, '10.0000');

    $reservation = $this->reserveStock($context, '3.0000', [
        'expires_at' => now()->subMinute()->toDateTimeString(),
    ]);

    $expired = app(StockReservationService::class)->expire();

    expect($expired)->toBe(1)
        ->and($reservation->refresh()->status)->toBe(StockReservationStatus::Expired)
        ->and($reservation->release_reason)->toBe('Reservation expired.')
        ->and(inventoryBalance($context)->reserved_quantity)->toBe('0.0000')
        ->and(inventoryBalance($context)->available_quantity)->toBe('10.0000');
});

it('consumes reserved stock through the central posting service', function () {
    $this->seedInventory();

    $context = $this->makeInventoryContext();
    $this->receiveStock($context, '10.0000');

    $reservation = $this->reserveStock($context, '4.0000');
    $line = $reservation->items()->firstOrFail();

    $transaction = $this->issueStock($context, '4.0000', $line->id);

    $balance = inventoryBalance($context);
    $line->refresh();
    $reservation->refresh();

    expect($transaction->isPosted())->toBeTrue()
        ->and($reservation->status)->toBe(StockReservationStatus::Consumed)
        ->and($line->consumed_quantity)->toBe('4.0000')
        ->and($line->outstandingQuantity())->toBe('0.0000')
        ->and($balance->on_hand_quantity)->toBe('6.0000')
        ->and($balance->reserved_quantity)->toBe('0.0000')
        ->and($balance->available_quantity)->toBe('6.0000')
        ->and(StockLedger::query()->count())->toBe(2);
});

it('keeps a partially consumed reservation open and resolves the remainder on release', function () {
    $this->seedInventory();

    $context = $this->makeInventoryContext();
    $this->receiveStock($context, '10.0000');

    $reservation = $this->reserveStock($context, '6.0000');
    $line = $reservation->items()->firstOrFail();

    $this->issueStock($context, '2.0000', $line->id);

    expect($reservation->refresh()->status)->toBe(StockReservationStatus::PartiallyConsumed)
        ->and($line->refresh()->outstandingQuantity())->toBe('4.0000')
        ->and(inventoryBalance($context)->reserved_quantity)->toBe('4.0000')
        ->and(inventoryBalance($context)->on_hand_quantity)->toBe('8.0000');

    $reservation = app(StockReservationService::class)->release(
        $reservation->refresh(),
        'Balance cancelled',
        [],
        $context['actor'],
    );

    expect($reservation->status)->toBe(StockReservationStatus::Consumed)
        ->and($line->refresh()->released_quantity)->toBe('4.0000')
        ->and(inventoryBalance($context)->reserved_quantity)->toBe('0.0000')
        ->and(inventoryBalance($context)->available_quantity)->toBe('8.0000');
});

it('rejects consumption above the reserved quantity and after release', function () {
    $this->seedInventory();

    $context = $this->makeInventoryContext();
    $this->receiveStock($context, '10.0000');

    $reservation = $this->reserveStock($context, '5.0000');
    $line = $reservation->items()->firstOrFail();

    expect(fn () => $this->issueStock($context, '6.0000', $line->id))
        ->toThrow(DomainException::class, 'only has 5.0000 outstanding');

    app(StockReservationService::class)->release($reservation, 'Released before issue', [], $context['actor']);

    expect(fn () => $this->issueStock($context, '1.0000', $line->id))
        ->toThrow(DomainException::class, 'no longer open');

    expect(inventoryBalance($context)->on_hand_quantity)->toBe('10.0000');
});

it('only reserves active warehouse dimensions', function () {
    $this->seedInventory();

    $context = $this->makeInventoryContext();
    $this->receiveStock($context, '10.0000');

    $quarantine = StockDimension::resolve(
        $context['warehouse']->id,
        type: StockDimensionType::Quarantine,
    );

    expect(fn () => $this->reserveStock($context, '1.0000', [
        'dimension_id' => $quarantine->id,
        'source' => $context['warehouse'],
    ]))->toThrow(DomainException::class, 'Only warehouse stock can be reserved');

    $context['dimension']->forceFill(['is_active' => false])->save();

    expect(fn () => $this->reserveStock($context, '1.0000', ['source' => $context['warehouse']]))
        ->toThrow(DomainException::class, 'unknown or inactive stock dimension');
});
