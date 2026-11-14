<?php

use App\Domain\Distribution\Services\StockIssueService;
use App\Domain\Inventory\Models\InventoryBalance;
use App\Domain\Inventory\Models\StockReservation;
use App\Domain\Inventory\Models\StockTransaction;
use App\Enums\IssueStatus;
use App\Enums\RequisitionStatus;
use App\Enums\StockMovementType;
use App\Enums\StockReservationStatus;

function issueBalance(array $context): InventoryBalance
{
    return InventoryBalance::query()
        ->where('warehouse_id', $context['warehouse']->id)
        ->where('stock_dimension_id', $context['dimension']->id)
        ->where('item_id', $context['item']->id)
        ->where('batch_key', 0)
        ->firstOrFail();
}

it('posts a stock issue that consumes the reservation and updates the requisition', function () {
    $context = $this->makeDistributionContext(stock: '100.0000');
    $requisition = $this->makeMaterialRequisition($context, '10.0000');
    $requisition = $this->submitAndApproveRequisition($requisition, $context);

    $issue = $this->reserveAndIssueRequisition($requisition, $context);

    $issue = $issue->refresh()->load('items');
    $requisition->refresh()->load('items');
    $item = $requisition->items->firstOrFail();
    $balance = issueBalance($context);
    $reservation = StockReservation::query()
        ->where('source_type', $requisition->getMorphClass())
        ->where('source_id', $requisition->id)
        ->firstOrFail();

    expect($issue->status)->toBe(IssueStatus::Posted)
        ->and($issue->document_number)->toStartWith('SIV')
        ->and((string) $issue->items->first()->issued_quantity)->toBe('10.0000')
        ->and((string) $issue->items->first()->unit_cost)->toBe('100.000000')
        ->and((string) $issue->items->first()->total_cost)->toBe('1000.00')
        ->and($requisition->status)->toBe(RequisitionStatus::Issued)
        ->and((string) $item->issued_quantity)->toBe('10.0000')
        ->and((string) $item->outstanding_quantity)->toBe('0.0000')
        ->and((string) $item->reserved_quantity)->toBe('0.0000')
        ->and($reservation->status)->toBe(StockReservationStatus::Consumed)
        ->and((string) $balance->on_hand_quantity)->toBe('90.0000')
        ->and((string) $balance->reserved_quantity)->toBe('0.0000')
        ->and((string) $balance->available_quantity)->toBe('90.0000');

    $transaction = StockTransaction::query()->findOrFail($issue->stock_transaction_id);

    expect($transaction->transaction_type)->toBe(StockMovementType::Issue);
});

it('supports partial issuance against an existing reservation', function () {
    $context = $this->makeDistributionContext(stock: '100.0000');
    $requisition = $this->makeMaterialRequisition($context, '10.0000');
    $requisition = $this->submitAndApproveRequisition($requisition, $context);

    $issue = $this->reserveAndIssueRequisition($requisition, $context, '4.0000');

    $requisition->refresh()->load('items');
    $item = $requisition->items->firstOrFail();
    $balance = issueBalance($context);
    $reservation = StockReservation::query()
        ->where('source_type', $requisition->getMorphClass())
        ->where('source_id', $requisition->id)
        ->firstOrFail();

    expect($issue->status)->toBe(IssueStatus::Posted)
        ->and($requisition->status)->toBe(RequisitionStatus::PartiallyIssued)
        ->and((string) $item->issued_quantity)->toBe('4.0000')
        ->and((string) $item->outstanding_quantity)->toBe('6.0000')
        ->and((string) $item->reserved_quantity)->toBe('6.0000')
        ->and($reservation->status)->toBe(StockReservationStatus::PartiallyConsumed)
        ->and((string) $balance->on_hand_quantity)->toBe('96.0000')
        ->and((string) $balance->reserved_quantity)->toBe('6.0000')
        ->and((string) $balance->available_quantity)->toBe('90.0000');
});

it('rejects issuing more than the reserved quantity', function () {
    $context = $this->makeDistributionContext(stock: '100.0000');
    $requisition = $this->makeMaterialRequisition($context, '10.0000');
    $requisition = $this->submitAndApproveRequisition($requisition, $context);

    expect(fn () => $this->reserveAndIssueRequisition($requisition, $context, '11.0000'))
        ->toThrow(DomainException::class);
});

it('rejects a stock issue against an unapproved requisition', function () {
    $context = $this->makeDistributionContext();
    $requisition = $this->makeMaterialRequisition($context, '10.0000');

    expect(fn () => $this->reserveAndIssueRequisition($requisition, $context))
        ->toThrow(DomainException::class);
});

it('lets the recipient acknowledge a posted issue and closes the requisition', function () {
    $context = $this->makeDistributionContext(stock: '100.0000');
    $requisition = $this->makeMaterialRequisition($context, '10.0000');
    $requisition = $this->submitAndApproveRequisition($requisition, $context);
    $issue = $this->reserveAndIssueRequisition($requisition, $context);

    $acknowledgement = app(StockIssueService::class)->acknowledge(
        $issue,
        $context['requester'],
        'Received in good order.',
        'signatures/requester.png',
        '127.0.0.1',
    );

    expect($acknowledgement->acknowledged_by)->toBe($context['requester']->id)
        ->and($acknowledgement->comment)->toBe('Received in good order.')
        ->and($issue->refresh()->isAcknowledged())->toBeTrue()
        ->and($requisition->refresh()->status)->toBe(RequisitionStatus::Acknowledged);
});

it('prevents anyone other than the recipient from acknowledging an issue', function () {
    $context = $this->makeDistributionContext(stock: '100.0000');
    $requisition = $this->makeMaterialRequisition($context, '10.0000');
    $requisition = $this->submitAndApproveRequisition($requisition, $context);
    $issue = $this->reserveAndIssueRequisition($requisition, $context);

    expect(fn () => app(StockIssueService::class)->acknowledge($issue, $context['store_officer']))
        ->toThrow(DomainException::class, 'Only the recipient can acknowledge this issue.');

    expect(fn () => app(StockIssueService::class)->acknowledge($issue, $context['destination_officer']))
        ->toThrow(DomainException::class, 'Only the recipient can acknowledge this issue.');
});

it('prevents an issue from being acknowledged twice', function () {
    $context = $this->makeDistributionContext(stock: '100.0000');
    $requisition = $this->makeMaterialRequisition($context, '10.0000');
    $requisition = $this->submitAndApproveRequisition($requisition, $context);
    $issue = $this->reserveAndIssueRequisition($requisition, $context);
    $service = app(StockIssueService::class);

    $service->acknowledge($issue, $context['requester']);

    expect(fn () => $service->acknowledge($issue->refresh(), $context['requester']))
        ->toThrow(DomainException::class, 'This issue has already been acknowledged.');
});

it('aggregates posted project consumption with quantity and value', function () {
    $context = $this->makeDistributionContext(stock: '100.0000');
    $project = $this->makeProject($context);
    $requisition = $this->makeMaterialRequisition($context, '10.0000', [
        'project_id' => $project->id,
    ]);
    $requisition = $this->submitAndApproveRequisition($requisition, $context);

    $this->reserveAndIssueRequisition($requisition, $context, '8.0000');

    $consumption = app(StockIssueService::class)->projectConsumption($project->id);

    expect($consumption['quantity'])->toBe('8.0000')
        ->and($consumption['value'])->toBe('800.00')
        ->and($consumption['lines'])->toHaveCount(1)
        ->and($consumption['lines'][0]['item_id'])->toBe($context['item']->id)
        ->and($consumption['lines'][0]['quantity'])->toBe('8.0000')
        ->and($consumption['lines'][0]['value'])->toBe('800.00');
});

it('reverses a posted issue and restores the outstanding quantity', function () {
    $context = $this->makeDistributionContext(stock: '100.0000');
    $requisition = $this->makeMaterialRequisition($context, '10.0000');
    $requisition = $this->submitAndApproveRequisition($requisition, $context);
    $issue = $this->reserveAndIssueRequisition($requisition, $context);

    $reversed = app(StockIssueService::class)->reverse($issue, $context['warehouse_manager'], 'Issued in error.');

    $requisition->refresh()->load('items');
    $balance = issueBalance($context);

    expect($reversed->status)->toBe(IssueStatus::Reversed)
        ->and((string) $requisition->items->first()->issued_quantity)->toBe('0.0000')
        ->and((string) $requisition->items->first()->outstanding_quantity)->toBe('10.0000')
        ->and((string) $balance->on_hand_quantity)->toBe('100.0000')
        ->and((string) $balance->available_quantity)->toBe('100.0000');
});
