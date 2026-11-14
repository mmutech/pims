<?php

use App\Domain\Organization\Models\BusinessUnit;
use App\Domain\Procurement\Models\PurchaseOrder;
use App\Domain\Procurement\Services\PurchaseOrderService;
use App\Domain\Procurement\Services\PurchaseRequisitionService;
use App\Domain\Procurement\Services\QuotationEvaluationService;
use App\Domain\Supplier\Models\Supplier;
use App\Domain\Warehouse\Models\Warehouse;
use App\DTOs\Procurement\PurchaseOrderData;
use App\DTOs\Procurement\PurchaseOrderItemData;
use App\Enums\PurchaseOrderStatus;
use App\Enums\SupplierQuotationStatus;
use App\Models\User;

beforeEach(function () {
    $this->seedProcurement();
    $this->makeProcurementFlow('purchase_requisition');
    $this->makeProcurementFlow('quotation_evaluation');
    $this->makeProcurementFlow('purchase_order');

    $this->businessUnit = BusinessUnit::factory()->create();
    $this->department = $this->makeDepartment();
    $this->warehouse = Warehouse::factory()->forBusinessUnit($this->businessUnit)->create();
    $this->requester = User::factory()
        ->withRole('Department Requester')
        ->create();
    $this->officer = User::factory()
        ->withRole('Procurement Officer')
        ->create();
    [$this->item, $this->unit] = $this->makeCatalogItem();
    $this->manager = $this->procurementApprover($this->warehouse);
    $this->supplierA = Supplier::factory()->approved()->create();
    $this->supplierB = Supplier::factory()->approved()->create();

    $requisition = $this->makeRequisition(
        $this->requester,
        $this->businessUnit,
        $this->department,
        $this->item,
        $this->unit,
    );

    app(PurchaseRequisitionService::class)->submit($requisition, $this->requester);
    $this->approveDocument($requisition, $this->manager);
    $this->requisition = $requisition->refresh();

    $this->rfq = $this->makeIssuedRfq(
        $this->requisition,
        $this->officer,
        [$this->supplierA, $this->supplierB],
    );

    $this->quotationA = $this->makeQuotation($this->rfq, $this->supplierA, $this->officer, '1250.00');
    $this->quotationB = $this->makeQuotation($this->rfq, $this->supplierB, $this->officer, '1350.00');

    $evaluation = $this->makeEvaluation($this->rfq, $this->quotationA, $this->officer);
    app(QuotationEvaluationService::class)->submit($evaluation, $this->officer);
    $this->approveDocument($evaluation, $this->manager);

    $this->quotationA->refresh();
});

it('creates a purchase order from an accepted quotation with fixed-precision totals', function () {
    $order = $this->makeOrderFromQuotation($this->quotationA, $this->warehouse, $this->officer);

    expect($order->status)->toBe(PurchaseOrderStatus::Draft)
        ->and($order->document_number)->toStartWith('PO/')
        ->and($order->supplier_id)->toBe($this->supplierA->id)
        ->and($order->purchase_requisition_id)->toBe($this->requisition->id)
        ->and($order->supplier_quotation_id)->toBe($this->quotationA->id)
        ->and($order->version)->toBe(1)
        ->and($order->subtotal)->toBe('12500.00')
        ->and($order->tax_amount)->toBe('937.50')
        ->and($order->total_amount)->toBe('13437.50')
        ->and($order->items)->toHaveCount(1);
});

it('requires sourcing before a purchase order can be raised', function () {
    config()->set('procurement.allow_unsourced_orders', false);

    $data = PurchaseOrderData::fromArray([
        'supplier_id' => $this->supplierA->id,
        'delivery_warehouse_id' => $this->warehouse->id,
        'created_by' => $this->officer->id,
        'items' => [[
            'item_id' => $this->item->id,
            'unit_of_measure_id' => $this->unit->id,
            'ordered_quantity' => '5',
            'unit_price' => '1250.00',
        ]],
    ]);

    expect(fn () => app(PurchaseOrderService::class)->create($data, $this->officer))
        ->toThrow(DomainException::class, 'requires an accepted quotation or approved requisition');
});

it('allows unsourced orders only when the configuration permits it', function () {
    config()->set('procurement.allow_unsourced_orders', true);

    $data = PurchaseOrderData::fromArray([
        'supplier_id' => $this->supplierA->id,
        'delivery_warehouse_id' => $this->warehouse->id,
        'created_by' => $this->officer->id,
        'items' => [[
            'item_id' => $this->item->id,
            'unit_of_measure_id' => $this->unit->id,
            'ordered_quantity' => '5',
            'unit_price' => '1250.00',
        ]],
    ]);

    $order = app(PurchaseOrderService::class)->create($data, $this->officer);

    expect($order->status)->toBe(PurchaseOrderStatus::Draft)
        ->and($order->total_amount)->toBe('6250.00');
});

it('rejects a sourcing quotation that has not been accepted', function () {
    $data = PurchaseOrderData::fromArray([
        'supplier_id' => $this->supplierB->id,
        'delivery_warehouse_id' => $this->warehouse->id,
        'created_by' => $this->officer->id,
        'supplier_quotation_id' => $this->quotationB->id,
        'items' => [[
            'item_id' => $this->item->id,
            'unit_of_measure_id' => $this->unit->id,
            'ordered_quantity' => '10',
            'unit_price' => '1350.00',
        ]],
    ]);

    expect(fn () => app(PurchaseOrderService::class)->create($data, $this->officer))
        ->toThrow(DomainException::class, 'must be accepted');
});

it('requires the purchase order supplier to match the accepted quotation', function () {
    $data = PurchaseOrderData::fromArray([
        'supplier_id' => $this->supplierB->id,
        'delivery_warehouse_id' => $this->warehouse->id,
        'created_by' => $this->officer->id,
        'supplier_quotation_id' => $this->quotationA->id,
        'items' => [[
            'item_id' => $this->item->id,
            'unit_of_measure_id' => $this->unit->id,
            'ordered_quantity' => '10',
            'unit_price' => '1250.00',
        ]],
    ]);

    expect(fn () => app(PurchaseOrderService::class)->create($data, $this->officer))
        ->toThrow(DomainException::class, 'must match the accepted quotation');
});

it('allows a purchase order to be sourced directly from an approved requisition', function () {
    $line = $this->requisition->items()->first();

    $data = new PurchaseOrderData(
        supplierId: $this->supplierB->id,
        deliveryWarehouseId: $this->warehouse->id,
        createdBy: $this->officer->id,
        purchaseRequisitionId: $this->requisition->id,
        currency: 'NGN',
        items: [
            new PurchaseOrderItemData(
                itemId: $this->item->id,
                unitOfMeasureId: $this->unit->id,
                orderedQuantity: '4',
                unitPrice: '1400.00',
                purchaseRequisitionItemId: $line->id,
            ),
        ],
    );

    $order = app(PurchaseOrderService::class)->create($data, $this->officer);

    expect($order->purchase_requisition_id)->toBe($this->requisition->id)
        ->and($order->supplier_quotation_id)->toBeNull()
        ->and($order->total_amount)->toBe('5600.00');
});

it('submits a purchase order for approval and approves it at the final stage', function () {
    $order = $this->makeOrderFromQuotation($this->quotationA, $this->warehouse, $this->officer);

    app(PurchaseOrderService::class)->submit($order, $this->officer);

    expect($order->fresh()->status)->toBe(PurchaseOrderStatus::PendingApproval);

    $this->approveDocument($order, $this->manager);

    $order->refresh();

    expect($order->status)->toBe(PurchaseOrderStatus::Approved)
        ->and($order->approved_by)->toBe($this->manager->id)
        ->and($order->approved_at)->not->toBeNull();
});

it('refuses to convert a quotation that is not accepted', function () {
    expect($this->quotationB->status)->toBe(SupplierQuotationStatus::Submitted);

    expect(fn () => $this->makeOrderFromQuotation($this->quotationB, $this->warehouse, $this->officer))
        ->toThrow(DomainException::class, 'Only accepted quotations');
});

it('scopes purchase orders to the creator or delivery warehouse access', function () {
    $order = $this->makeOrderFromQuotation($this->quotationA, $this->warehouse, $this->officer);

    $outsider = User::factory()
        ->withRole('Procurement Officer')
        ->create();

    expect($this->officer->can('view', $order))->toBeTrue()
        ->and($outsider->can('view', $order))->toBeFalse()
        ->and(PurchaseOrder::query()->visibleTo($this->officer)->whereKey($order->id)->exists())->toBeTrue()
        ->and(PurchaseOrder::query()->visibleTo($outsider)->whereKey($order->id)->exists())->toBeFalse();
});
