<?php

use App\Domain\Organization\Models\BusinessUnit;
use App\Domain\Procurement\Models\PurchaseOrder;
use App\Domain\Procurement\Models\PurchaseOrderItem;
use App\Domain\Procurement\Services\PurchaseOrderAmendmentService;
use App\Domain\Warehouse\Models\Warehouse;
use App\Domain\Workflow\Models\ApprovalRequest;
use App\Enums\PurchaseOrderAmendmentStatus;
use App\Livewire\Procurement\Orders\Show as OrderShow;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->seedProcurement();
    $this->makeProcurementFlow('purchase_order_amendment');

    $this->businessUnit = BusinessUnit::factory()->create();
    $this->warehouse = Warehouse::factory()->forBusinessUnit($this->businessUnit)->create();
    $this->officer = User::factory()
        ->withRole('Procurement Officer')
        ->create();
    $this->manager = $this->procurementApprover($this->warehouse);
    $this->approver = $this->procurementApprover($this->warehouse);
    [$this->item, $this->unit] = $this->makeCatalogItem();

    $this->order = PurchaseOrder::factory()->approved()->create([
        'delivery_warehouse_id' => $this->warehouse->id,
        'created_by' => $this->officer->id,
        'version' => 1,
    ]);

    $this->line = PurchaseOrderItem::create([
        'purchase_order_id' => $this->order->id,
        'item_id' => $this->item->id,
        'unit_of_measure_id' => $this->unit->id,
        'ordered_quantity' => '10.0000',
        'received_quantity' => '0.0000',
        'rejected_quantity' => '0.0000',
        'unit_price' => '1000.0000',
        'tax_rate' => '7.5000',
        'discount_amount' => '0.00',
        'line_total' => '10000.00',
        'sort_order' => 0,
    ]);

    $this->order->recalculateTotals();
});

it('creates a numbered amendment with original and amended values', function () {
    $amendment = app(PurchaseOrderAmendmentService::class)->create(
        $this->order,
        'Supplier increased the unit price.',
        [[
            'purchase_order_item_id' => $this->line->id,
            'amended_quantity' => '12',
            'amended_unit_price' => '1100',
        ]],
        $this->manager,
    );

    $line = $amendment->items()->first();

    expect($amendment->amendment_number)->toBe(1)
        ->and($amendment->status)->toBe(PurchaseOrderAmendmentStatus::Draft)
        ->and($amendment->requested_by)->toBe($this->manager->id)
        ->and($amendment->items)->toHaveCount(1)
        ->and($line->original_quantity)->toBe('10.0000')
        ->and($line->amended_quantity)->toBe('12.0000')
        ->and($line->original_unit_price)->toBe('1000.0000')
        ->and($line->amended_unit_price)->toBe('1100.0000')
        ->and($line->original_line_total)->toBe('10000.00')
        ->and($line->amended_line_total)->toBe('13200.00');
});

it('only allows approved purchase orders to be amended', function () {
    $draft = PurchaseOrder::factory()->create([
        'delivery_warehouse_id' => $this->warehouse->id,
    ]);

    expect(fn () => app(PurchaseOrderAmendmentService::class)->create(
        $draft,
        'Attempted amendment',
        [[
            'purchase_order_item_id' => $this->line->id,
            'amended_quantity' => '11',
            'amended_unit_price' => '1000',
        ]],
        $this->manager,
    ))->toThrow(DomainException::class, 'Only approved purchase orders');
});

it('requires a reason and at least one amendment line', function () {
    expect(fn () => app(PurchaseOrderAmendmentService::class)->create(
        $this->order,
        '   ',
        [[
            'purchase_order_item_id' => $this->line->id,
            'amended_quantity' => '11',
            'amended_unit_price' => '1000',
        ]],
        $this->manager,
    ))->toThrow(DomainException::class, 'amendment reason is required');

    expect(fn () => app(PurchaseOrderAmendmentService::class)->create(
        $this->order,
        'No lines supplied',
        [],
        $this->manager,
    ))->toThrow(DomainException::class, 'at least one line');
});

it('applies an approved amendment and bumps the purchase order version', function () {
    $amendment = app(PurchaseOrderAmendmentService::class)->create(
        $this->order,
        'Supplier increased the unit price.',
        [[
            'purchase_order_item_id' => $this->line->id,
            'amended_quantity' => '12',
            'amended_unit_price' => '1100',
        ]],
        $this->manager,
    );

    app(PurchaseOrderAmendmentService::class)->submit($amendment, $this->manager);

    expect($amendment->fresh()->status)->toBe(PurchaseOrderAmendmentStatus::PendingApproval);

    $this->approveDocument($amendment, $this->approver);

    $amendment->refresh();
    $this->order->refresh();
    $this->line->refresh();

    expect($amendment->status)->toBe(PurchaseOrderAmendmentStatus::Approved)
        ->and($amendment->approved_by)->toBe($this->approver->id)
        ->and($this->order->version)->toBe(2)
        ->and($this->line->ordered_quantity)->toBe('12.0000')
        ->and($this->line->unit_price)->toBe('1100.0000')
        ->and($this->line->line_total)->toBe('13200.00')
        ->and($this->order->subtotal)->toBe('13200.00')
        ->and($this->order->tax_amount)->toBe('990.00')
        ->and($this->order->total_amount)->toBe('14190.00');
});

it('rejects amendments that reduce quantity below the received quantity', function () {
    $this->line->forceFill(['received_quantity' => '5.0000', 'rejected_quantity' => '0.0000'])->save();

    expect(fn () => app(PurchaseOrderAmendmentService::class)->create(
        $this->order,
        'Reduce the order after partial delivery.',
        [[
            'purchase_order_item_id' => $this->line->id,
            'amended_quantity' => '4',
            'amended_unit_price' => '1000',
        ]],
        $this->manager,
    ))->toThrow(DomainException::class, 'cannot fall below the quantity already received');
});

it('numbers successive amendments sequentially', function () {
    $service = app(PurchaseOrderAmendmentService::class);

    $first = $service->create($this->order, 'First change', [[
        'purchase_order_item_id' => $this->line->id,
        'amended_quantity' => '11',
        'amended_unit_price' => '1000',
    ]], $this->manager);

    $second = $service->create($this->order, 'Second change', [[
        'purchase_order_item_id' => $this->line->id,
        'amended_quantity' => '12',
        'amended_unit_price' => '1000',
    ]], $this->manager);

    expect($first->amendment_number)->toBe(1)
        ->and($second->amendment_number)->toBe(2);
});

it('cancelling an amendment leaves the purchase order unchanged', function () {
    $amendment = app(PurchaseOrderAmendmentService::class)->create(
        $this->order,
        'Price correction',
        [[
            'purchase_order_item_id' => $this->line->id,
            'amended_quantity' => '12',
            'amended_unit_price' => '1100',
        ]],
        $this->manager,
    );

    app(PurchaseOrderAmendmentService::class)->submit($amendment, $this->manager);
    $cancelled = app(PurchaseOrderAmendmentService::class)->cancel(
        $amendment->fresh(),
        $this->manager,
        'Raised in error',
    );

    expect($cancelled->status)->toBe(PurchaseOrderAmendmentStatus::Cancelled)
        ->and($this->order->fresh()->version)->toBe(1)
        ->and($this->line->fresh()->ordered_quantity)->toBe('10.0000');
});

it('snapshots the amended total on the approval request', function () {
    $amendment = app(PurchaseOrderAmendmentService::class)->create(
        $this->order,
        'Supplier increased the unit price.',
        [[
            'purchase_order_item_id' => $this->line->id,
            'amended_quantity' => '12',
            'amended_unit_price' => '1100',
        ]],
        $this->manager,
    );

    expect($amendment->projectedTotals())->toBe([
        'subtotal' => '13200.00',
        'tax' => '990.00',
        'total' => '14190.00',
    ]);

    app(PurchaseOrderAmendmentService::class)->submit($amendment, $this->manager);

    $request = ApprovalRequest::query()
        ->where('document_type', 'purchase_order_amendment')
        ->where('document_id', $amendment->id)
        ->firstOrFail();

    expect($request->metadata['amount'])->toBe('14190.00')
        ->and($request->metadata['currency'])->toBe($this->order->currency);
});

it('links an amendment approval from the purchase order screen', function () {
    $amendment = app(PurchaseOrderAmendmentService::class)->create(
        $this->order,
        'Supplier increased the unit price.',
        [[
            'purchase_order_item_id' => $this->line->id,
            'amended_quantity' => '12',
            'amended_unit_price' => '1100',
        ]],
        $this->manager,
    );

    app(PurchaseOrderAmendmentService::class)->submit($amendment, $this->manager);

    $request = ApprovalRequest::query()
        ->where('document_type', 'purchase_order_amendment')
        ->where('document_id', $amendment->id)
        ->firstOrFail();

    $this->actingAs($this->manager);

    Livewire::test(OrderShow::class, ['order' => $this->order])
        ->assertSee('Amendment #1')
        ->assertSee('Open approval')
        ->assertSee(route('approvals.show', $request), escape: false);
});

it('only grants amendment rights to roles with the amend permission', function () {
    expect($this->manager->can('amend', $this->order))->toBeTrue()
        ->and($this->officer->can('amend', $this->order))->toBeFalse();
});
