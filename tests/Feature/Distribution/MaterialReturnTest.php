<?php

use App\Domain\Distribution\Models\MaterialReturn;
use App\Domain\Distribution\Services\MaterialReturnService;
use App\Domain\Inventory\Models\InventoryBalance;
use App\Domain\Inventory\Models\StockDimension;
use App\Domain\Inventory\Models\StockTransaction;
use App\Enums\ConditionDisposition;
use App\Enums\IssueStatus;
use App\Enums\MaterialCondition;
use App\Enums\MaterialReturnType;
use App\Enums\ReturnStatus;
use App\Enums\StockDimensionType;
use App\Enums\StockMovementType;

function returnDimensionBalance(array $context, StockDimensionType $type): ?InventoryBalance
{
    $dimension = StockDimension::resolve($context['warehouse']->id, type: $type);

    return InventoryBalance::query()
        ->where('warehouse_id', $context['warehouse']->id)
        ->where('stock_dimension_id', $dimension->id)
        ->where('item_id', $context['item']->id)
        ->where('batch_key', 0)
        ->first();
}

it('creates a draft return linked to the original issue', function () {
    $context = $this->makeDistributionContext(stock: '100.0000');
    $issue = $this->postedRequisitionIssue($context, '10.0000');

    $return = $this->makeMaterialReturn($issue, $context, '4.0000');

    expect($return->status)->toBe(ReturnStatus::Draft)
        ->and($return->document_number)->toStartWith('RET')
        ->and($return->return_type)->toBe(MaterialReturnType::Project)
        ->and($return->original_issue_id)->toBe($issue->id)
        ->and($return->items)->toHaveCount(1)
        ->and((string) $return->items->first()->returned_quantity)->toBe('4.0000')
        ->and((string) $return->items->first()->unit_cost)->toBe('100.000000');
});

it('rejects supplier returns because they belong to the receiving module', function () {
    $context = $this->makeDistributionContext(stock: '100.0000');
    $issue = $this->postedRequisitionIssue($context, '10.0000');

    expect(fn () => $this->makeMaterialReturn($issue, $context, '4.0000', MaterialCondition::Good, [
        'return_type' => MaterialReturnType::Supplier->value,
    ]))->toThrow(DomainException::class, 'Supplier returns are handled by the receiving module.');
});

it('requires project and department returns to reference the original issue', function () {
    $context = $this->makeDistributionContext(stock: '100.0000');
    $issue = $this->postedRequisitionIssue($context, '10.0000');

    expect(fn () => $this->makeMaterialReturn($issue, $context, '4.0000', MaterialCondition::Good, [
        'original_issue_id' => null,
    ]))->toThrow(
        DomainException::class,
        'Project and department returns must reference the original stock issue.',
    );
});

it('rejects a return warehouse that differs from the issue warehouse', function () {
    $context = $this->makeDistributionContext(stock: '100.0000');
    $issue = $this->postedRequisitionIssue($context, '10.0000');

    expect(fn () => $this->makeMaterialReturn($issue, $context, '4.0000', MaterialCondition::Good, [
        'warehouse_id' => $context['destination_warehouse']->id,
    ]))->toThrow(
        DomainException::class,
        'The return warehouse does not match the original issue warehouse.',
    );
});

it('rejects returning more than was issued', function () {
    $context = $this->makeDistributionContext(stock: '100.0000');
    $issue = $this->postedRequisitionIssue($context, '10.0000');

    expect(fn () => $this->makeMaterialReturn($issue, $context, '11.0000'))
        ->toThrow(DomainException::class);
});

it('counts previous returns when validating the returnable quantity', function () {
    $context = $this->makeDistributionContext(stock: '100.0000');
    $issue = $this->postedRequisitionIssue($context, '10.0000');

    $this->makeMaterialReturn($issue, $context, '6.0000');

    expect(fn () => $this->makeMaterialReturn($issue, $context, '5.0000'))
        ->toThrow(DomainException::class);
});

it('requires a condition assessment before posting', function () {
    $context = $this->makeDistributionContext(stock: '100.0000');
    $issue = $this->postedRequisitionIssue($context, '10.0000');
    $return = $this->makeMaterialReturn($issue, $context, '4.0000');

    expect(fn () => app(MaterialReturnService::class)->post($return, $context['warehouse_manager']))
        ->toThrow(DomainException::class, 'Only assessed material returns can be posted.');
});

it('requires accepted and rejected quantities to equal the returned quantity', function () {
    $context = $this->makeDistributionContext(stock: '100.0000');
    $issue = $this->postedRequisitionIssue($context, '10.0000');
    $return = $this->makeMaterialReturn($issue, $context, '4.0000');
    $item = $return->items->firstOrFail();

    expect(fn () => app(MaterialReturnService::class)->assess($return, [
        $item->id => [
            'condition' => MaterialCondition::Good->value,
            'accepted_quantity' => '2.0000',
            'rejected_quantity' => '1.0000',
            'disposition' => ConditionDisposition::ReturnToStock->value,
        ],
    ], $context['quality_inspector']))->toThrow(
        DomainException::class,
        'Accepted and rejected quantities must equal the returned quantity.',
    );
});

it('prevents a return from being assessed twice', function () {
    $context = $this->makeDistributionContext(stock: '100.0000');
    $issue = $this->postedRequisitionIssue($context, '10.0000');
    $return = $this->makeMaterialReturn($issue, $context, '4.0000');
    $item = $return->items->firstOrFail();
    $service = app(MaterialReturnService::class);

    $service->assess($return, [
        $item->id => [
            'condition' => MaterialCondition::Good->value,
            'accepted_quantity' => '4.0000',
            'rejected_quantity' => '0.0000',
            'disposition' => ConditionDisposition::ReturnToStock->value,
        ],
    ], $context['quality_inspector']);

    expect(fn () => $service->assess($return->refresh(), [
        $item->id => [
            'condition' => MaterialCondition::Good->value,
            'accepted_quantity' => '4.0000',
            'rejected_quantity' => '0.0000',
            'disposition' => ConditionDisposition::ReturnToStock->value,
        ],
    ], $context['quality_inspector']))->toThrow(
        DomainException::class,
        'This return has already been assessed or posted.',
    );
});

it('returns good-condition material to available warehouse stock', function () {
    $context = $this->makeDistributionContext(stock: '100.0000');
    $issue = $this->postedRequisitionIssue($context, '10.0000');
    $return = $this->makeMaterialReturn($issue, $context, '4.0000');
    $item = $return->items->firstOrFail();
    $service = app(MaterialReturnService::class);

    $service->assess($return, [
        $item->id => [
            'condition' => MaterialCondition::Good->value,
            'accepted_quantity' => '4.0000',
            'rejected_quantity' => '0.0000',
            'disposition' => ConditionDisposition::ReturnToStock->value,
            'findings' => 'Unused and undamaged.',
        ],
    ], $context['quality_inspector']);

    $return = $service->post($return->refresh(), $context['warehouse_manager']);
    $balance = returnDimensionBalance($context, StockDimensionType::Warehouse);
    $transaction = $return->stock_transaction_id
        ? StockTransaction::query()->find($return->stock_transaction_id)
        : null;

    expect($return->status)->toBe(ReturnStatus::Posted)
        ->and($return->posted_at)->not->toBeNull()
        ->and((string) $return->items->first()->accepted_quantity)->toBe('4.0000')
        ->and($return->items->first()->stock_dimension_id)->not->toBeNull()
        ->and((string) $balance->on_hand_quantity)->toBe('94.0000')
        ->and((string) $balance->available_quantity)->toBe('94.0000')
        ->and($transaction)->not->toBeNull()
        ->and($transaction->transaction_type)->toBe(StockMovementType::ReturnIn);
});

it('only returns the accepted portion of a good-condition line to stock', function () {
    $context = $this->makeDistributionContext(stock: '100.0000');
    $issue = $this->postedRequisitionIssue($context, '10.0000');
    $return = $this->makeMaterialReturn($issue, $context, '4.0000');
    $item = $return->items->firstOrFail();
    $service = app(MaterialReturnService::class);

    $service->assess($return, [
        $item->id => [
            'condition' => MaterialCondition::Good->value,
            'accepted_quantity' => '3.0000',
            'rejected_quantity' => '1.0000',
            'disposition' => ConditionDisposition::ReturnToStock->value,
        ],
    ], $context['quality_inspector']);

    $service->post($return->refresh(), $context['warehouse_manager']);

    $balance = returnDimensionBalance($context, StockDimensionType::Warehouse);

    expect((string) $balance->on_hand_quantity)->toBe('93.0000')
        ->and((string) $balance->available_quantity)->toBe('93.0000');
});

it('routes damaged returns to quarantine and keeps them unavailable', function () {
    $context = $this->makeDistributionContext(stock: '100.0000');
    $issue = $this->postedRequisitionIssue($context, '10.0000');
    $return = $this->makeMaterialReturn($issue, $context, '4.0000', MaterialCondition::Damaged);
    $item = $return->items->firstOrFail();
    $service = app(MaterialReturnService::class);

    $service->assess($return, [
        $item->id => [
            'condition' => MaterialCondition::Damaged->value,
            'accepted_quantity' => '4.0000',
            'rejected_quantity' => '0.0000',
            'disposition' => ConditionDisposition::Quarantine->value,
            'findings' => 'Cracked casing on arrival.',
        ],
    ], $context['quality_inspector']);

    $return = $service->post($return->refresh(), $context['warehouse_manager']);

    $available = returnDimensionBalance($context, StockDimensionType::Warehouse);
    $quarantine = returnDimensionBalance($context, StockDimensionType::Quarantine);

    expect($return->status)->toBe(ReturnStatus::Posted)
        ->and((string) $available->on_hand_quantity)->toBe('90.0000')
        ->and((string) $available->available_quantity)->toBe('90.0000')
        ->and($quarantine)->not->toBeNull()
        ->and((string) $quarantine->on_hand_quantity)->toBe('4.0000')
        ->and($quarantine->dimension->type)->toBe(StockDimensionType::Quarantine)
        ->and($quarantine->dimension->type->isAvailableForIssue())->toBeFalse();
});

it('creates no stock movement for disposal-pending returns', function () {
    $context = $this->makeDistributionContext(stock: '100.0000');
    $issue = $this->postedRequisitionIssue($context, '10.0000');
    $return = $this->makeMaterialReturn($issue, $context, '4.0000', MaterialCondition::DisposalPending);
    $item = $return->items->firstOrFail();
    $service = app(MaterialReturnService::class);

    $service->assess($return, [
        $item->id => [
            'condition' => MaterialCondition::DisposalPending->value,
            'accepted_quantity' => '4.0000',
            'rejected_quantity' => '0.0000',
            'disposition' => ConditionDisposition::Dispose->value,
            'findings' => 'Beyond economical repair.',
        ],
    ], $context['quality_inspector']);

    $return = $service->post($return->refresh(), $context['warehouse_manager']);
    $available = returnDimensionBalance($context, StockDimensionType::Warehouse);
    $quarantine = returnDimensionBalance($context, StockDimensionType::Quarantine);

    expect($return->status)->toBe(ReturnStatus::Posted)
        ->and($return->stock_transaction_id)->toBeNull()
        ->and((string) $available->on_hand_quantity)->toBe('90.0000')
        ->and($quarantine)->toBeNull();
});

it('rejects good-condition returns that are not returned to stock', function () {
    $context = $this->makeDistributionContext(stock: '100.0000');
    $issue = $this->postedRequisitionIssue($context, '10.0000');
    $return = $this->makeMaterialReturn($issue, $context, '4.0000', MaterialCondition::Good);
    $item = $return->items->firstOrFail();

    expect(fn () => app(MaterialReturnService::class)->assess($return, [
        $item->id => [
            'condition' => MaterialCondition::Good->value,
            'accepted_quantity' => '4.0000',
            'rejected_quantity' => '0.0000',
            'disposition' => ConditionDisposition::Quarantine->value,
        ],
    ], $context['quality_inspector']))->toThrow(
        DomainException::class,
        'Good-condition returns must be returned to stock.',
    );
});

it('rejects non-good returns that try to re-enter available stock', function () {
    $context = $this->makeDistributionContext(stock: '100.0000');
    $issue = $this->postedRequisitionIssue($context, '10.0000');
    $return = $this->makeMaterialReturn($issue, $context, '4.0000', MaterialCondition::Defective);
    $item = $return->items->firstOrFail();

    expect(fn () => app(MaterialReturnService::class)->assess($return, [
        $item->id => [
            'condition' => MaterialCondition::Defective->value,
            'accepted_quantity' => '4.0000',
            'rejected_quantity' => '0.0000',
            'disposition' => ConditionDisposition::ReturnToStock->value,
        ],
    ], $context['quality_inspector']))->toThrow(
        DomainException::class,
        'Only good-condition returns can re-enter available stock.',
    );
});

it('does not double post an already posted return', function () {
    $context = $this->makeDistributionContext(stock: '100.0000');
    $issue = $this->postedRequisitionIssue($context, '10.0000');
    $return = $this->makeMaterialReturn($issue, $context, '4.0000');
    $item = $return->items->firstOrFail();
    $service = app(MaterialReturnService::class);

    $service->assess($return, [
        $item->id => [
            'condition' => MaterialCondition::Good->value,
            'accepted_quantity' => '4.0000',
            'rejected_quantity' => '0.0000',
            'disposition' => ConditionDisposition::ReturnToStock->value,
        ],
    ], $context['quality_inspector']);

    $first = $service->post($return->refresh(), $context['warehouse_manager']);
    $second = $service->post($first->refresh(), $context['warehouse_manager']);

    $balance = returnDimensionBalance($context, StockDimensionType::Warehouse);

    expect($second->id)->toBe($first->id)
        ->and((string) $balance->on_hand_quantity)->toBe('94.0000');
});

it('only allows posted issues to be returned against', function () {
    $context = $this->makeDistributionContext(stock: '100.0000');
    $requisition = $this->submitAndApproveRequisition(
        $this->makeMaterialRequisition($context, '10.0000'),
        $context,
    );

    $issue = $this->draftRequisitionIssue($requisition, $context, '4.0000');

    expect($issue->status)->toBe(IssueStatus::Draft);

    expect(fn () => $this->makeMaterialReturn($issue, $context, '2.0000'))
        ->toThrow(DomainException::class, 'Only posted stock issues can be returned against.');
});

it('cancels a draft return and excludes it from returned quantities', function () {
    $context = $this->makeDistributionContext(stock: '100.0000');
    $issue = $this->postedRequisitionIssue($context, '10.0000');
    $service = app(MaterialReturnService::class);

    $return = $this->makeMaterialReturn($issue, $context, '6.0000');
    $service->cancel($return, $context['requester'], 'Raised in error.');

    expect($return->refresh()->status)->toBe(ReturnStatus::Cancelled);

    $replacement = $this->makeMaterialReturn($issue, $context, '10.0000');

    expect($replacement)->toBeInstanceOf(MaterialReturn::class)
        ->and((string) $replacement->items->first()->returned_quantity)->toBe('10.0000');
});
