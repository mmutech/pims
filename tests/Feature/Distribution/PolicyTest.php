<?php

use App\Domain\Distribution\Models\MaterialRequisition;
use App\Domain\Distribution\Models\MaterialReturn;
use App\Domain\Distribution\Models\StockIssue;
use App\Domain\Distribution\Models\StockTransfer;
use App\Domain\Distribution\Services\MaterialRequisitionService;
use App\Domain\Distribution\Services\MaterialReturnService;
use App\Domain\Distribution\Services\StockIssueService;
use App\Enums\ConditionDisposition;
use App\Enums\MaterialCondition;
use App\Models\User;

it('enforces distribution create permissions per role', function () {
    $context = $this->makeDistributionContext(stock: '0');

    expect($context['requester']->can('create', MaterialRequisition::class))->toBeTrue()
        ->and($context['quality_inspector']->can('create', MaterialRequisition::class))->toBeFalse()
        ->and($context['store_officer']->can('create', StockIssue::class))->toBeTrue()
        ->and($context['requester']->can('create', StockIssue::class))->toBeFalse()
        ->and($context['store_officer']->can('create', StockTransfer::class))->toBeTrue()
        ->and($context['requester']->can('create', StockTransfer::class))->toBeFalse()
        ->and($context['requester']->can('create', MaterialReturn::class))->toBeTrue()
        ->and($context['quality_inspector']->can('create', MaterialReturn::class))->toBeFalse();
});

it('scopes requisition visibility to the requester and warehouse users', function () {
    $context = $this->makeDistributionContext(stock: '0');
    $requisition = $this->makeMaterialRequisition($context, '5.0000');

    $outsider = User::factory()
        ->withRole('Department Requester')
        ->create();

    expect($context['requester']->can('view', $requisition))->toBeTrue()
        ->and($context['store_officer']->can('view', $requisition))->toBeTrue()
        ->and($outsider->can('view', $requisition))->toBeFalse()
        ->and($outsider->can('update', $requisition))->toBeFalse();
});

it('blocks requisition mutations once the document leaves draft', function () {
    $context = $this->makeDistributionContext(stock: '0');
    $requisition = $this->makeMaterialRequisition($context, '5.0000');

    expect($context['requester']->can('update', $requisition))->toBeTrue()
        ->and($context['requester']->can('submit', $requisition))->toBeTrue()
        ->and($context['requester']->can('delete', $requisition))->toBeTrue();

    app(MaterialRequisitionService::class)
        ->submit($requisition, $context['requester']);

    $requisition->refresh();

    expect($context['requester']->can('update', $requisition))->toBeFalse()
        ->and($context['requester']->can('submit', $requisition))->toBeFalse()
        ->and($context['requester']->can('cancel', $requisition))->toBeTrue()
        ->and($context['warehouse_manager']->can('adjustApprovedQuantities', $requisition))->toBeTrue();
});

it('allows only the recipient to acknowledge a posted issue', function () {
    $context = $this->makeDistributionContext(stock: '100.0000');
    $issue = $this->postedRequisitionIssue($context, '10.0000');

    expect($context['requester']->can('acknowledge', $issue))->toBeTrue()
        ->and($context['store_officer']->can('acknowledge', $issue))->toBeFalse()
        ->and($context['destination_officer']->can('acknowledge', $issue))->toBeFalse()
        ->and($context['quality_inspector']->can('acknowledge', $issue))->toBeFalse();

    app(StockIssueService::class)
        ->acknowledge($issue, $context['requester']);

    expect($context['requester']->can('acknowledge', $issue->refresh()))->toBeFalse();
});

it('separates issue posting and reversal duties by permission', function () {
    $context = $this->makeDistributionContext(stock: '100.0000');
    $requisition = $this->submitAndApproveRequisition(
        $this->makeMaterialRequisition($context, '10.0000'),
        $context,
    );
    $draft = $this->draftRequisitionIssue($requisition, $context, '4.0000');

    expect($context['store_officer']->can('post', $draft))->toBeTrue()
        ->and($context['requester']->can('post', $draft))->toBeFalse()
        ->and($context['quality_inspector']->can('post', $draft))->toBeFalse();

    $posted = app(StockIssueService::class)
        ->post($draft, $context['store_officer']);

    expect($context['store_officer']->can('post', $posted))->toBeFalse()
        ->and($context['store_officer']->can('reverse', $posted))->toBeTrue()
        ->and($context['quality_inspector']->can('reverse', $posted))->toBeFalse()
        ->and($context['requester']->can('reverse', $posted))->toBeFalse();
});

it('scopes transfer dispatch and receipt to the respective warehouses', function () {
    $context = $this->makeDistributionContext(stock: '100.0000');
    $transfer = $this->submitAndApproveTransfer(
        $this->makeStockTransfer($context, '10.0000'),
        $context,
    );

    expect($context['store_officer']->can('dispatch', $transfer))->toBeTrue()
        ->and($context['destination_officer']->can('dispatch', $transfer))->toBeFalse()
        ->and($context['warehouse_manager']->can('dispatch', $transfer))->toBeTrue()
        ->and($context['store_officer']->can('receive', $transfer))->toBeFalse()
        ->and($context['destination_officer']->can('receive', $transfer))->toBeFalse();

    $dispatch = $this->dispatchTransfer($transfer, $context);
    $transfer->refresh();

    expect($context['destination_officer']->can('receive', $transfer))->toBeTrue()
        ->and($context['warehouse_manager']->can('receive', $transfer))->toBeTrue()
        ->and($context['store_officer']->can('receive', $transfer))->toBeFalse();

    $dispatchItem = $dispatch->refresh()->load('items')->items->firstOrFail();

    $this->receiveTransfer($dispatch, $context, [[
        'dispatch_item_id' => $dispatchItem->id,
        'received_quantity' => '9.0000',
        'damaged_quantity' => '1.0000',
    ]]);

    $transfer->refresh();

    expect($transfer->hasOpenDiscrepancies())->toBeTrue()
        ->and($context['store_officer']->can('resolveDiscrepancy', $transfer))->toBeTrue()
        ->and($context['destination_officer']->can('resolveDiscrepancy', $transfer))->toBeTrue()
        ->and($context['quality_inspector']->can('resolveDiscrepancy', $transfer))->toBeFalse();
});

it('prevents the returner or creator from assessing a material return', function () {
    $context = $this->makeDistributionContext(stock: '100.0000');
    $issue = $this->postedRequisitionIssue($context, '10.0000');
    $returner = $this->makeDistributionUser('Store Officer', $context, $context['warehouse']);
    $creator = $this->makeDistributionUser('Warehouse Manager', $context, $context['warehouse']);

    $returnedByReturner = $this->makeMaterialReturn($issue, $context, '2.0000', MaterialCondition::Good, [
        'returned_by' => $returner->id,
    ]);

    $createdByCreator = $this->makeMaterialReturn(
        $issue,
        $context,
        '2.0000',
        MaterialCondition::Good,
        ['returned_by' => $context['requester']->id],
        $creator,
    );

    expect($returner->can('assess', $returnedByReturner))->toBeFalse()
        ->and($creator->can('assess', $createdByCreator))->toBeFalse()
        ->and($context['quality_inspector']->can('assess', $returnedByReturner))->toBeTrue()
        ->and($context['quality_inspector']->can('assess', $createdByCreator))->toBeTrue()
        ->and($context['requester']->can('assess', $returnedByReturner))->toBeFalse();
});

it('requires assessment and the post permission before a return can be posted', function () {
    $context = $this->makeDistributionContext(stock: '100.0000');
    $issue = $this->postedRequisitionIssue($context, '10.0000');
    $return = $this->makeMaterialReturn($issue, $context, '4.0000');
    $item = $return->items->firstOrFail();

    expect($context['warehouse_manager']->can('post', $return))->toBeFalse()
        ->and($context['quality_inspector']->can('post', $return))->toBeFalse();

    app(MaterialReturnService::class)->assess($return, [
        $item->id => [
            'condition' => MaterialCondition::Good->value,
            'accepted_quantity' => '4.0000',
            'rejected_quantity' => '0.0000',
            'disposition' => ConditionDisposition::ReturnToStock->value,
        ],
    ], $context['quality_inspector']);

    $return->refresh();

    expect($context['warehouse_manager']->can('post', $return))->toBeTrue()
        ->and($context['store_officer']->can('post', $return))->toBeTrue()
        ->and($context['quality_inspector']->can('post', $return))->toBeFalse()
        ->and($context['warehouse_manager']->can('update', $return))->toBeFalse()
        ->and($context['warehouse_manager']->can('cancel', $return))->toBeTrue();
});
