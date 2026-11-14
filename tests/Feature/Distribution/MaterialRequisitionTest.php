<?php

use App\Domain\Distribution\Models\MaterialBackorder;
use App\Domain\Distribution\Models\MaterialRequisition;
use App\Domain\Distribution\Services\MaterialRequisitionService;
use App\Domain\Inventory\Models\InventoryBalance;
use App\Domain\Inventory\Models\StockReservation;
use App\Enums\MaterialBackorderStatus;
use App\Enums\RequisitionStatus;
use App\Enums\StockReservationStatus;

function requisitionBalance(array $context): InventoryBalance
{
    return InventoryBalance::query()
        ->where('warehouse_id', $context['warehouse']->id)
        ->where('stock_dimension_id', $context['dimension']->id)
        ->where('item_id', $context['item']->id)
        ->where('batch_key', 0)
        ->firstOrFail();
}

it('creates a draft material requisition with a generated document number', function () {
    $context = $this->makeDistributionContext(stock: '0');

    $requisition = $this->makeMaterialRequisition($context, '12.0000');

    expect($requisition->status)->toBe(RequisitionStatus::Draft)
        ->and($requisition->document_number)->toStartWith('MR')
        ->and($requisition->items)->toHaveCount(1)
        ->and((string) $requisition->items->first()->requested_quantity)->toBe('12.0000');
});

it('requires a purpose and positive quantities', function () {
    $context = $this->makeDistributionContext(stock: '0');

    expect(fn () => $this->makeMaterialRequisition($context, '0.0000'))
        ->toThrow(DomainException::class, 'Requested quantities must be greater than zero.');

    expect(fn () => $this->makeMaterialRequisition($context, '5.0000', ['purpose' => '  ']))
        ->toThrow(DomainException::class, 'A requisition purpose is required.');
});

it('submits a requisition into the approval engine and approves full quantities', function () {
    $context = $this->makeDistributionContext();
    $requisition = $this->makeMaterialRequisition($context, '15.0000');

    $service = app(MaterialRequisitionService::class);
    $service->submit($requisition, $context['requester']);

    expect($requisition->refresh()->status)->toBe(RequisitionStatus::Submitted)
        ->and($this->approvalRequestFor($requisition))->not->toBeNull();

    $this->approveDocument($requisition->refresh(), $context['warehouse_manager']);

    $requisition->refresh()->load('items');
    $item = $requisition->items->first();

    expect($requisition->status)->toBe(RequisitionStatus::Approved)
        ->and((string) $item->approved_quantity)->toBe('15.0000')
        ->and((string) $item->outstanding_quantity)->toBe('15.0000')
        ->and($requisition->approved_by)->toBe($context['warehouse_manager']->id);
});

it('supports partial approval by reducing an approved quantity while pending', function () {
    $context = $this->makeDistributionContext();
    $requisition = $this->makeMaterialRequisition($context, '20.0000');
    $service = app(MaterialRequisitionService::class);

    $service->submit($requisition, $context['requester']);
    $item = $requisition->refresh()->items()->firstOrFail();

    $service->updateApprovedQuantities($requisition->refresh(), [
        $item->id => '8.0000',
    ], $context['warehouse_manager']);

    $this->approveDocument($requisition->refresh(), $context['warehouse_manager']);

    $requisition->refresh()->load('items');
    $item = $requisition->items->first();

    expect($requisition->status)->toBe(RequisitionStatus::PartiallyApproved)
        ->and((string) $item->approved_quantity)->toBe('8.0000')
        ->and((string) $item->outstanding_quantity)->toBe('8.0000');
});

it('rejects approval quantities above the requested quantity', function () {
    $context = $this->makeDistributionContext();
    $requisition = $this->makeMaterialRequisition($context, '10.0000');
    $service = app(MaterialRequisitionService::class);

    $service->submit($requisition, $context['requester']);
    $item = $requisition->refresh()->items()->firstOrFail();

    expect(fn () => $service->updateApprovedQuantities($requisition->refresh(), [
        $item->id => '11.0000',
    ], $context['warehouse_manager']))
        ->toThrow(DomainException::class, 'Approved quantities cannot exceed the requested quantity.');
});

it('reserves approved quantities against available stock without moving on-hand stock', function () {
    $context = $this->makeDistributionContext(stock: '100.0000');
    $requisition = $this->makeMaterialRequisition($context, '20.0000');
    $requisition = $this->submitAndApproveRequisition($requisition, $context);

    app(MaterialRequisitionService::class)->reserve($requisition->refresh(), $context['store_officer']);

    $requisition->refresh()->load('items');
    $item = $requisition->items->firstOrFail();
    $balance = requisitionBalance($context);
    $reservation = StockReservation::query()
        ->where('source_type', $requisition->getMorphClass())
        ->where('source_id', $requisition->id)
        ->firstOrFail();

    expect($requisition->status)->toBe(RequisitionStatus::Reserved)
        ->and((string) $item->reserved_quantity)->toBe('20.0000')
        ->and($reservation->status)->toBe(StockReservationStatus::Active)
        ->and((string) $balance->on_hand_quantity)->toBe('100.0000')
        ->and((string) $balance->reserved_quantity)->toBe('20.0000')
        ->and((string) $balance->available_quantity)->toBe('80.0000');
});

it('creates an open backorder for quantities that cannot be reserved', function () {
    $context = $this->makeDistributionContext(stock: '5.0000');
    $requisition = $this->makeMaterialRequisition($context, '20.0000');
    $requisition = $this->submitAndApproveRequisition($requisition, $context);

    app(MaterialRequisitionService::class)->reserve($requisition->refresh(), $context['store_officer']);

    $requisition->refresh()->load('items');
    $item = $requisition->items->firstOrFail();
    $backorder = MaterialBackorder::query()
        ->where('material_requisition_item_id', $item->id)
        ->firstOrFail();

    expect($requisition->status)->toBe(RequisitionStatus::Reserved)
        ->and((string) $item->reserved_quantity)->toBe('5.0000')
        ->and($backorder->status)->toBe(MaterialBackorderStatus::Open)
        ->and((string) $backorder->outstanding_quantity)->toBe('15.0000');
});

it('prevents a second open reservation for the same requisition', function () {
    $context = $this->makeDistributionContext();
    $requisition = $this->makeMaterialRequisition($context, '10.0000');
    $requisition = $this->submitAndApproveRequisition($requisition, $context);
    $service = app(MaterialRequisitionService::class);

    $service->reserve($requisition->refresh(), $context['store_officer']);

    expect(fn () => $service->reserve($requisition->refresh(), $context['store_officer']))
        ->toThrow(DomainException::class, 'An open reservation already exists for this requisition.');
});

it('releases reservations and cancels backorders when a requisition is cancelled', function () {
    $context = $this->makeDistributionContext(stock: '5.0000');
    $requisition = $this->makeMaterialRequisition($context, '20.0000');
    $requisition = $this->submitAndApproveRequisition($requisition, $context);
    $service = app(MaterialRequisitionService::class);

    $service->reserve($requisition->refresh(), $context['store_officer']);
    $service->cancel($requisition->refresh(), $context['warehouse_manager'], 'Works order cancelled.');

    $requisition->refresh()->load('items');
    $balance = requisitionBalance($context);
    $reservation = StockReservation::query()
        ->where('source_type', $requisition->getMorphClass())
        ->where('source_id', $requisition->id)
        ->firstOrFail();
    $backorder = MaterialBackorder::query()
        ->where('material_requisition_item_id', $requisition->items->first()->id)
        ->firstOrFail();

    expect($requisition->status)->toBe(RequisitionStatus::Cancelled)
        ->and((string) $requisition->items->first()->reserved_quantity)->toBe('0.0000')
        ->and($reservation->status)->toBe(StockReservationStatus::Released)
        ->and($backorder->status)->toBe(MaterialBackorderStatus::Cancelled)
        ->and((string) $balance->reserved_quantity)->toBe('0.0000')
        ->and((string) $balance->available_quantity)->toBe('5.0000');
});

it('does not allow reserving an unapproved requisition', function () {
    $context = $this->makeDistributionContext();
    $requisition = $this->makeMaterialRequisition($context, '10.0000');

    expect(fn () => app(MaterialRequisitionService::class)->reserve($requisition, $context['store_officer']))
        ->toThrow(DomainException::class, 'Only approved requisitions can reserve stock.');
});

it('deletes draft requisitions but not approved ones', function () {
    $context = $this->makeDistributionContext();
    $service = app(MaterialRequisitionService::class);

    $draft = $this->makeMaterialRequisition($context, '4.0000');
    $service->delete($draft);

    expect(MaterialRequisition::query()->find($draft->id))->toBeNull();

    $approved = $this->submitAndApproveRequisition(
        $this->makeMaterialRequisition($context, '4.0000'),
        $context,
    );

    expect(fn () => $service->delete($approved))
        ->toThrow(DomainException::class, 'Only draft or returned requisitions can be deleted.');
});
