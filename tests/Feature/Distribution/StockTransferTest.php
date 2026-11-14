<?php

use App\Domain\Distribution\Services\StockTransferService;
use App\Domain\Inventory\Models\InventoryBalance;
use App\Domain\Inventory\Models\StockDimension;
use App\Enums\StockDimensionType;
use App\Enums\TransferDiscrepancyResolution;
use App\Enums\TransferDiscrepancyType;
use App\Enums\TransferStatus;

function transferDimensionBalance(int $warehouseId, StockDimensionType $type, array $context): ?InventoryBalance
{
    $dimension = StockDimension::resolve($warehouseId, type: $type);

    return InventoryBalance::query()
        ->where('warehouse_id', $warehouseId)
        ->where('stock_dimension_id', $dimension->id)
        ->where('item_id', $context['item']->id)
        ->where('batch_key', 0)
        ->first();
}

it('requires approval before a transfer can be dispatched', function () {
    $context = $this->makeDistributionContext();
    $transfer = $this->makeStockTransfer($context, '10.0000');

    expect(fn () => $this->dispatchTransfer($transfer, $context))
        ->toThrow(DomainException::class, 'This transfer is not ready for dispatch.');
});

it('dispatches an approved transfer into in-transit stock', function () {
    $context = $this->makeDistributionContext(stock: '100.0000');
    $transfer = $this->submitAndApproveTransfer($this->makeStockTransfer($context, '20.0000'), $context);

    $dispatch = $this->dispatchTransfer($transfer, $context);

    $transfer->refresh();
    $source = transferDimensionBalance($context['warehouse']->id, StockDimensionType::Warehouse, $context);
    $transit = transferDimensionBalance($context['warehouse']->id, StockDimensionType::InTransit, $context);

    expect($transfer->status)->toBe(TransferStatus::InTransit)
        ->and($dispatch->document_number)->toStartWith('TRD')
        ->and((string) $dispatch->items->first()->quantity)->toBe('20.0000')
        ->and((string) $source->on_hand_quantity)->toBe('80.0000')
        ->and((string) $transit->on_hand_quantity)->toBe('20.0000')
        ->and(transferDimensionBalance(
            $context['destination_warehouse']->id,
            StockDimensionType::Warehouse,
            $context,
        ))->toBeNull();
});

it('supports partial dispatches of an approved transfer', function () {
    $context = $this->makeDistributionContext(stock: '100.0000');
    $transfer = $this->submitAndApproveTransfer($this->makeStockTransfer($context, '10.0000'), $context);

    $this->dispatchTransfer($transfer, $context, '4.0000');

    expect($transfer->refresh()->status)->toBe(TransferStatus::PartiallyDispatched);

    $this->dispatchTransfer($transfer->refresh(), $context, '6.0000');

    $transit = transferDimensionBalance($context['warehouse']->id, StockDimensionType::InTransit, $context);

    expect($transfer->refresh()->status)->toBe(TransferStatus::InTransit)
        ->and((string) $transit->on_hand_quantity)->toBe('10.0000');
});

it('rejects dispatching more than the approved quantity', function () {
    $context = $this->makeDistributionContext(stock: '100.0000');
    $transfer = $this->submitAndApproveTransfer($this->makeStockTransfer($context, '10.0000'), $context);

    expect(fn () => $this->dispatchTransfer($transfer, $context, '11.0000'))
        ->toThrow(DomainException::class, 'Only 10.0000 remains approved for dispatch on this line.');
});

it('receives good stock into the destination and quarantines damaged stock', function () {
    $context = $this->makeDistributionContext(stock: '100.0000');
    $transfer = $this->submitAndApproveTransfer($this->makeStockTransfer($context, '10.0000'), $context);
    $dispatch = $this->dispatchTransfer($transfer, $context);
    $dispatchItem = $dispatch->refresh()->load('items')->items->firstOrFail();

    $receipt = $this->receiveTransfer($dispatch, $context, [[
        'dispatch_item_id' => $dispatchItem->id,
        'received_quantity' => '6.0000',
        'damaged_quantity' => '2.0000',
        'missing_quantity' => '2.0000',
    ]]);

    $transfer->refresh();
    $destination = transferDimensionBalance(
        $context['destination_warehouse']->id,
        StockDimensionType::Warehouse,
        $context,
    );
    $quarantine = transferDimensionBalance(
        $context['destination_warehouse']->id,
        StockDimensionType::Quarantine,
        $context,
    );
    $transit = transferDimensionBalance($context['warehouse']->id, StockDimensionType::InTransit, $context);

    expect($receipt->document_number)->toStartWith('TRR')
        ->and($transfer->status)->toBe(TransferStatus::PartiallyReceived)
        ->and((string) $destination->on_hand_quantity)->toBe('6.0000')
        ->and((string) $quarantine->on_hand_quantity)->toBe('2.0000')
        ->and((string) $transit->on_hand_quantity)->toBe('2.0000')
        ->and((string) $transfer->discrepancies()->count())->toBe('2');
});

it('records damaged and missing discrepancies for review', function () {
    $context = $this->makeDistributionContext(stock: '100.0000');
    $transfer = $this->submitAndApproveTransfer($this->makeStockTransfer($context, '10.0000'), $context);
    $dispatch = $this->dispatchTransfer($transfer, $context);
    $dispatchItem = $dispatch->refresh()->load('items')->items->firstOrFail();

    $this->receiveTransfer($dispatch, $context, [[
        'dispatch_item_id' => $dispatchItem->id,
        'received_quantity' => '6.0000',
        'damaged_quantity' => '2.0000',
        'missing_quantity' => '2.0000',
    ]]);

    $damaged = $transfer->discrepancies()->where('variance_type', TransferDiscrepancyType::Damaged->value)->firstOrFail();
    $missing = $transfer->discrepancies()->where('variance_type', TransferDiscrepancyType::Missing->value)->firstOrFail();

    expect((string) $damaged->quantity)->toBe('2.0000')
        ->and($damaged->resolution)->toBe(TransferDiscrepancyResolution::Pending)
        ->and((string) $missing->quantity)->toBe('2.0000')
        ->and($missing->resolution)->toBe(TransferDiscrepancyResolution::Pending);
});

it('completes a transfer once all discrepancies are resolved', function () {
    $context = $this->makeDistributionContext(stock: '100.0000');
    $transfer = $this->submitAndApproveTransfer($this->makeStockTransfer($context, '10.0000'), $context);
    $dispatch = $this->dispatchTransfer($transfer, $context);
    $dispatchItem = $dispatch->refresh()->load('items')->items->firstOrFail();
    $service = app(StockTransferService::class);

    $this->receiveTransfer($dispatch, $context, [[
        'dispatch_item_id' => $dispatchItem->id,
        'received_quantity' => '6.0000',
        'damaged_quantity' => '2.0000',
        'missing_quantity' => '2.0000',
    ]]);

    $damaged = $transfer->discrepancies()->where('variance_type', TransferDiscrepancyType::Damaged->value)->firstOrFail();
    $missing = $transfer->discrepancies()->where('variance_type', TransferDiscrepancyType::Missing->value)->firstOrFail();

    $service->resolveDiscrepancy($damaged, TransferDiscrepancyResolution::WriteOff, $context['warehouse_manager']);
    $service->resolveDiscrepancy($missing, TransferDiscrepancyResolution::ReturnToSource, $context['warehouse_manager']);

    $transfer->refresh();
    $source = transferDimensionBalance($context['warehouse']->id, StockDimensionType::Warehouse, $context);
    $transit = transferDimensionBalance($context['warehouse']->id, StockDimensionType::InTransit, $context);

    expect($transfer->status)->toBe(TransferStatus::Completed)
        ->and($transfer->completed_at)->not->toBeNull()
        ->and((string) $source->on_hand_quantity)->toBe('92.0000')
        ->and((string) $transit->on_hand_quantity)->toBe('0.0000');
});

it('rejects receipts that exceed the outstanding dispatched quantity', function () {
    $context = $this->makeDistributionContext(stock: '100.0000');
    $transfer = $this->submitAndApproveTransfer($this->makeStockTransfer($context, '10.0000'), $context);
    $dispatch = $this->dispatchTransfer($transfer, $context);
    $dispatchItem = $dispatch->refresh()->load('items')->items->firstOrFail();

    expect(fn () => $this->receiveTransfer($dispatch, $context, [[
        'dispatch_item_id' => $dispatchItem->id,
        'received_quantity' => '11.0000',
    ]]))->toThrow(DomainException::class);
});

it('rejects resolving an already resolved discrepancy', function () {
    $context = $this->makeDistributionContext(stock: '100.0000');
    $transfer = $this->submitAndApproveTransfer($this->makeStockTransfer($context, '10.0000'), $context);
    $dispatch = $this->dispatchTransfer($transfer, $context);
    $dispatchItem = $dispatch->refresh()->load('items')->items->firstOrFail();
    $service = app(StockTransferService::class);

    $this->receiveTransfer($dispatch, $context, [[
        'dispatch_item_id' => $dispatchItem->id,
        'damaged_quantity' => '1.0000',
        'received_quantity' => '9.0000',
    ]]);

    $damaged = $transfer->discrepancies()->where('variance_type', TransferDiscrepancyType::Damaged->value)->firstOrFail();
    $service->resolveDiscrepancy($damaged, TransferDiscrepancyResolution::Claim, $context['warehouse_manager']);

    expect(fn () => $service->resolveDiscrepancy(
        $damaged->refresh(),
        TransferDiscrepancyResolution::WriteOff,
        $context['warehouse_manager'],
    ))->toThrow(DomainException::class, 'This discrepancy has already been resolved.');
});
