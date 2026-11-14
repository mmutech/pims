<?php

use App\Domain\Catalog\Models\Item;
use App\Domain\Inventory\Models\StockLedger;
use App\Domain\Inventory\Models\StockTransaction;
use App\Domain\Receiving\Models\GoodsReceivedNote;
use App\Domain\Receiving\Services\GoodsReceivedNoteService;
use App\Domain\Receiving\Services\QualityInspectionService;
use App\Domain\Receiving\Services\SupplierReturnService;
use App\DTOs\Receiving\QualityInspectionData;
use App\DTOs\Receiving\SupplierReturnData;
use App\Enums\GoodsReceivedNoteStatus;
use App\Enums\PurchaseOrderStatus;
use App\Enums\ReceiptStatus;
use App\Enums\ReturnStatus;
use App\Enums\StockMovementType;
use App\Enums\StockTransactionStatus;

it('records a partial delivery and posts only the delivered quantity', function () {
    $this->seedReceiving();

    $context = $this->makeReceivingContext(orderedQuantity: '10.0000');
    $officer = $this->receivingActor('Store Officer');

    $sra = $this->submitReceiptAdvice(
        $this->makeReceiptAdvice($context['order_item'], $officer, '6.0000', '6.0000'),
        $officer,
    );

    $this->verifyReceiptAdvice($sra->refresh(), $officer);

    $grn = app(GoodsReceivedNoteService::class)->createFromSra($sra->refresh(), $officer);
    $grn->load('items');

    expect($grn->items)->toHaveCount(1)
        ->and($grn->items->first()->accepted_quantity)->toBe('6.0000');

    app(GoodsReceivedNoteService::class)->post($grn, $officer);

    expect($context['order_item']->refresh()->received_quantity)->toBe('6.0000')
        ->and($context['order']->refresh()->status)->toBe(PurchaseOrderStatus::PartiallyReceived)
        ->and($this->onHand($context['item'], $context['warehouse']))->toBe('6.0000')
        ->and($sra->refresh()->status)->toBe(ReceiptStatus::Posted);
});

it('separates accepted and rejected quantities and never stocks rejected goods', function () {
    $this->seedReceiving();

    $item = Item::factory()->create([
        'requires_quality_inspection' => true,
        'standard_cost' => '1500.0000',
    ]);

    $context = $this->makeReceivingContext($item, orderedQuantity: '10.0000');
    $officer = $this->receivingActor('Store Officer');
    $inspector = $this->receivingActor('Quality Inspector');

    $sra = $this->submitReceiptAdvice(
        $this->makeReceiptAdvice($context['order_item'], $officer, '10.0000', '10.0000'),
        $officer,
    );

    $this->verifyReceiptAdvice($sra->refresh(), $officer);

    $sra->refresh();
    expect($sra->status)->toBe(ReceiptStatus::PendingInspection);

    $inspection = app(QualityInspectionService::class)->start($sra, $inspector);
    $line = $sra->items()->firstOrFail();

    app(QualityInspectionService::class)->record($inspection, QualityInspectionData::fromArray([
        'sra_id' => $sra->id,
        'inspector_id' => $inspector->id,
        'items' => [[
            'sra_item_id' => $line->id,
            'inspected_quantity' => '10.0000',
            'accepted_quantity' => '7.0000',
            'rejected_quantity' => '3.0000',
            'quarantined_quantity' => '0.0000',
            'findings' => [[
                'finding_type' => 'specification',
                'severity' => 'major',
                'description' => 'Three units had cracked casings.',
            ]],
        ]],
        'summary' => 'Three of ten units rejected.',
    ]), $inspector);

    $sra->refresh();
    expect($sra->status)->toBe(ReceiptStatus::Inspected);

    $grn = app(GoodsReceivedNoteService::class)->createFromSra($sra, $officer);
    $grn->load('items');

    expect($grn->items->first()->accepted_quantity)->toBe('7.0000')
        ->and($grn->items->first()->rejected_quantity)->toBe('3.0000');

    app(GoodsReceivedNoteService::class)->post($grn, $officer);

    $grn->refresh();
    $orderItem = $context['order_item']->refresh();

    expect($this->onHand($context['item'], $context['warehouse']))->toBe('7.0000')
        ->and($orderItem->received_quantity)->toBe('7.0000')
        ->and($orderItem->rejected_quantity)->toBe('3.0000')
        ->and($grn->stockTransaction->items)->toHaveCount(1)
        ->and($grn->stockTransaction->items->first()->quantity)->toBe('7.0000')
        ->and(StockLedger::query()->where('quantity_in', '>', 0)->count())->toBe(1);
});

it('blocks goods receipt until a required inspection is complete', function () {
    $this->seedReceiving();

    $item = Item::factory()->create(['requires_quality_inspection' => true]);
    $context = $this->makeReceivingContext($item);
    $officer = $this->receivingActor('Store Officer');
    $inspector = $this->receivingActor('Quality Inspector');

    $sra = $this->submitReceiptAdvice(
        $this->makeReceiptAdvice($context['order_item'], $officer, '5.0000', '5.0000'),
        $officer,
    );

    $this->verifyReceiptAdvice($sra->refresh(), $officer);

    $sra->refresh();
    expect($sra->status)->toBe(ReceiptStatus::PendingInspection);

    expect(fn () => app(GoodsReceivedNoteService::class)->createFromSra($sra, $officer))
        ->toThrow(DomainException::class);

    $inspection = app(QualityInspectionService::class)->start($sra, $inspector);
    $line = $sra->items()->firstOrFail();

    app(QualityInspectionService::class)->record($inspection, QualityInspectionData::fromArray([
        'sra_id' => $sra->id,
        'inspector_id' => $inspector->id,
        'items' => [[
            'sra_item_id' => $line->id,
            'inspected_quantity' => '5.0000',
            'accepted_quantity' => '5.0000',
            'rejected_quantity' => '0.0000',
        ]],
        'summary' => 'All units passed inspection.',
    ]), $inspector);

    $grn = app(GoodsReceivedNoteService::class)->createFromSra($sra->refresh(), $officer);

    expect($grn)->toBeInstanceOf(GoodsReceivedNote::class)
        ->and($grn->items()->count())->toBe(1);
});

it('posts a goods received note exactly once when posted repeatedly', function () {
    $this->seedReceiving();

    $context = $this->makeReceivingContext();
    $officer = $this->receivingActor('Store Officer');

    $sra = $this->submitReceiptAdvice(
        $this->makeReceiptAdvice($context['order_item'], $officer, '5.0000', '5.0000'),
        $officer,
    );

    $this->verifyReceiptAdvice($sra->refresh(), $officer);

    $grn = app(GoodsReceivedNoteService::class)->createFromSra($sra->refresh(), $officer);

    $first = app(GoodsReceivedNoteService::class)->post($grn, $officer);
    $second = app(GoodsReceivedNoteService::class)->post($first->refresh(), $officer);

    expect($second->id)->toBe($first->id)
        ->and($second->status)->toBe(GoodsReceivedNoteStatus::Posted)
        ->and(StockTransaction::query()->count())->toBe(1)
        ->and(StockTransaction::query()->where('idempotency_key', 'like', '%:grn_post')->count())->toBe(1)
        ->and(StockLedger::query()->count())->toBe(1)
        ->and($first->refresh()->stockTransaction->sourceLabel())->toBe($grn->document_number)
        ->and($this->onHand($context['item'], $context['warehouse']))->toBe('5.0000');
});

it('posts supplier returns out of stock and rejects returns above availability', function () {
    $this->seedReceiving();

    $context = $this->makeReceivingContext();
    $officer = $this->receivingActor('Store Officer');

    $sra = $this->submitReceiptAdvice(
        $this->makeReceiptAdvice($context['order_item'], $officer, '5.0000', '5.0000'),
        $officer,
    );

    $this->verifyReceiptAdvice($sra->refresh(), $officer);

    $grn = app(GoodsReceivedNoteService::class)->createFromSra($sra->refresh(), $officer);
    app(GoodsReceivedNoteService::class)->post($grn, $officer);

    $return = app(SupplierReturnService::class)->create(SupplierReturnData::fromArray([
        'supplier_id' => $context['supplier']->id,
        'warehouse_id' => $context['warehouse']->id,
        'created_by' => $officer->id,
        'grn_id' => $grn->id,
        'reason' => 'Damaged on delivery.',
        'items' => [[
            'item_id' => $context['item']->id,
            'unit_of_measure_id' => $context['unit']->id,
            'quantity' => '3.0000',
            'unit_cost' => '1500.000000',
        ]],
    ]), $officer);

    $posted = app(SupplierReturnService::class)->post($return, $officer);

    expect($posted->status)->toBe(ReturnStatus::Posted)
        ->and($this->onHand($context['item'], $context['warehouse']))->toBe('2.0000')
        ->and(StockTransaction::query()->where('transaction_type', StockMovementType::ReturnOut->value)->count())->toBe(1);

    $overReturn = app(SupplierReturnService::class)->create(SupplierReturnData::fromArray([
        'supplier_id' => $context['supplier']->id,
        'warehouse_id' => $context['warehouse']->id,
        'created_by' => $officer->id,
        'reason' => 'Attempt to return more than on hand.',
        'items' => [[
            'item_id' => $context['item']->id,
            'unit_of_measure_id' => $context['unit']->id,
            'quantity' => '3.0000',
            'unit_cost' => '1500.000000',
        ]],
    ]), $officer);

    expect(fn () => app(SupplierReturnService::class)->post($overReturn, $officer))
        ->toThrow(DomainException::class, 'Insufficient available stock');

    expect($overReturn->refresh()->status)->toBe(ReturnStatus::Draft)
        ->and($this->onHand($context['item'], $context['warehouse']))->toBe('2.0000');
});

it('reverses a posted goods received note and restores purchase order quantities', function () {
    $this->seedReceiving();

    $context = $this->makeReceivingContext();
    $officer = $this->receivingActor('Store Officer');

    $sra = $this->submitReceiptAdvice(
        $this->makeReceiptAdvice($context['order_item'], $officer, '5.0000', '5.0000'),
        $officer,
    );

    $this->verifyReceiptAdvice($sra->refresh(), $officer);

    $grn = app(GoodsReceivedNoteService::class)->createFromSra($sra->refresh(), $officer);
    app(GoodsReceivedNoteService::class)->post($grn, $officer);

    $grn->refresh();
    $original = $grn->stockTransaction;

    app(GoodsReceivedNoteService::class)->reverse($grn, $officer, 'Supplier collected the wrong items.');

    $grn->refresh();
    $original->refresh();

    expect($grn->status)->toBe(GoodsReceivedNoteStatus::Reversed)
        ->and($original->status)->toBe(StockTransactionStatus::Reversed)
        ->and($original->reversed_by_transaction_id)->not->toBeNull()
        ->and($context['order_item']->refresh()->received_quantity)->toBe('0.0000')
        ->and($context['order']->refresh()->status)->toBe(PurchaseOrderStatus::Approved)
        ->and($this->onHand($context['item'], $context['warehouse']))->toBe('0.0000')
        ->and(StockTransaction::query()->whereKey($original->reversed_by_transaction_id)->value('transaction_type'))
        ->toBe(StockMovementType::ReversalOut);
});

it('rejects a receipt advice that exceeds the outstanding order quantity', function () {
    $this->seedReceiving();

    $context = $this->makeReceivingContext(orderedQuantity: '5.0000');
    $officer = $this->receivingActor('Store Officer');

    expect(fn () => $this->makeReceiptAdvice($context['order_item'], $officer, '6.0000', '6.0000'))
        ->toThrow(DomainException::class, 'exceeds the outstanding order quantity');
});

it('marks a purchase order fully received and refuses further deliveries', function () {
    $this->seedReceiving();

    $context = $this->makeReceivingContext(orderedQuantity: '5.0000');
    $officer = $this->receivingActor('Store Officer');

    $sra = $this->submitReceiptAdvice(
        $this->makeReceiptAdvice($context['order_item'], $officer, '5.0000', '5.0000'),
        $officer,
    );

    $this->verifyReceiptAdvice($sra->refresh(), $officer);

    $grn = app(GoodsReceivedNoteService::class)->createFromSra($sra->refresh(), $officer);
    app(GoodsReceivedNoteService::class)->post($grn, $officer);

    expect($context['order']->refresh()->status)->toBe(PurchaseOrderStatus::FullyReceived);

    expect(fn () => $this->makeReceiptAdvice($context['order_item'], $officer, '1.0000', '1.0000'))
        ->toThrow(DomainException::class, 'Only approved or partially received purchase orders can accept deliveries.');
});
