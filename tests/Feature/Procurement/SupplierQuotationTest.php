<?php

use App\Domain\Organization\Models\BusinessUnit;
use App\Domain\Procurement\Models\RequestForQuotation;
use App\Domain\Procurement\Models\RfqItem;
use App\Domain\Procurement\Models\SupplierQuotation;
use App\Domain\Procurement\Services\PurchaseRequisitionService;
use App\Domain\Procurement\Services\SupplierQuotationService;
use App\Domain\Supplier\Models\Supplier;
use App\DTOs\Procurement\QuotationData;
use App\Enums\RfqSupplierStatus;
use App\Enums\SupplierQuotationStatus;
use App\Models\User;

beforeEach(function () {
    $this->seedProcurement();
    $this->makeProcurementFlow('purchase_requisition');

    $this->businessUnit = BusinessUnit::factory()->create();
    $this->department = $this->makeDepartment();
    $this->requester = User::factory()
        ->withRole('Department Requester')
        ->create();
    $this->officer = User::factory()
        ->withRole('Procurement Officer')
        ->create();
    [$this->item, $this->unit] = $this->makeCatalogItem();
    $this->manager = $this->procurementApprover();
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

    $this->rfq = $this->makeIssuedRfq(
        $requisition->refresh(),
        $this->officer,
        [$this->supplierA, $this->supplierB],
    );
});

it('captures a supplier quotation and recalculates totals with fixed precision', function () {
    $quotation = $this->makeQuotation($this->rfq, $this->supplierA, $this->officer);

    expect($quotation->status)->toBe(SupplierQuotationStatus::Submitted)
        ->and($quotation->document_number)->toStartWith('QT/')
        ->and($quotation->subtotal)->toBe('12500.00')
        ->and($quotation->tax_amount)->toBe('937.50')
        ->and($quotation->total_amount)->toBe('13437.50')
        ->and($quotation->items)->toHaveCount(1)
        ->and($quotation->submitted_at)->not->toBeNull();
});

it('rejects a quotation from a supplier who was not invited', function () {
    $stranger = Supplier::factory()->approved()->create();

    expect(fn () => app(SupplierQuotationService::class)->create(
        QuotationData::fromArray([
            'request_for_quotation_id' => $this->rfq->id,
            'supplier_id' => $stranger->id,
            'items' => [[
                'rfq_item_id' => $this->rfq->items()->first()->id,
                'quantity' => '10',
                'unit_price' => '1200.00',
            ]],
        ]),
        $this->officer,
    ))->toThrow(DomainException::class, 'was not invited');
});

it('prevents a duplicate quotation for the same supplier and RFQ', function () {
    $this->makeQuotation($this->rfq, $this->supplierA, $this->officer);

    expect(fn () => app(SupplierQuotationService::class)->create(
        QuotationData::fromArray([
            'request_for_quotation_id' => $this->rfq->id,
            'supplier_id' => $this->supplierA->id,
            'items' => [[
                'rfq_item_id' => $this->rfq->items()->first()->id,
                'quantity' => '10',
                'unit_price' => '1300.00',
            ]],
        ]),
        $this->officer,
    ))->toThrow(DomainException::class, 'A quotation already exists');
});

it('rejects quotations against an RFQ that is not open', function () {
    $draftRfq = RequestForQuotation::factory()->create([
        'business_unit_id' => $this->businessUnit->id,
        'created_by' => $this->officer->id,
    ]);

    $draftItem = RfqItem::factory()->create([
        'request_for_quotation_id' => $draftRfq->id,
        'item_id' => $this->item->id,
        'unit_of_measure_id' => $this->unit->id,
        'quantity' => '10.0000',
    ]);

    expect(fn () => app(SupplierQuotationService::class)->create(
        QuotationData::fromArray([
            'request_for_quotation_id' => $draftRfq->id,
            'supplier_id' => $this->supplierA->id,
            'items' => [[
                'rfq_item_id' => $draftItem->id,
                'quantity' => '10',
                'unit_price' => '1200.00',
            ]],
        ]),
        $this->officer,
    ))->toThrow(DomainException::class, 'not open for quotations');
});

it('rejects quotation lines that do not belong to the RFQ', function () {
    $otherQuotation = $this->makeQuotation($this->rfq, $this->supplierB, $this->officer);

    expect(fn () => app(SupplierQuotationService::class)->create(
        QuotationData::fromArray([
            'request_for_quotation_id' => $this->rfq->id,
            'supplier_id' => $this->supplierA->id,
            'items' => [[
                'rfq_item_id' => $otherQuotation->items()->first()->rfq_item_id + 999,
                'quantity' => '10',
                'unit_price' => '1200.00',
            ]],
        ]),
        $this->officer,
    ))->toThrow(DomainException::class, 'must reference items from the RFQ');
});

it('marks the invited supplier as responded when a quotation is submitted', function () {
    $this->makeQuotation($this->rfq, $this->supplierA, $this->officer);

    $invite = $this->rfq->suppliers()->where('supplier_id', $this->supplierA->id)->first();

    expect($invite->status)->toBe(RfqSupplierStatus::Responded)
        ->and($invite->responded_at)->not->toBeNull();
});

it('allows a submitted quotation to be withdrawn and reopens the supplier response', function () {
    $quotation = $this->makeQuotation($this->rfq, $this->supplierA, $this->officer);

    $withdrawn = app(SupplierQuotationService::class)->withdraw($quotation, $this->officer);

    $invite = $this->rfq->suppliers()->where('supplier_id', $this->supplierA->id)->first();

    expect($withdrawn->status)->toBe(SupplierQuotationStatus::Withdrawn)
        ->and($invite->status)->toBe(RfqSupplierStatus::NoResponse);
});

it('scopes quotations to the creator', function () {
    $quotation = $this->makeQuotation($this->rfq, $this->supplierA, $this->officer);

    $outsider = User::factory()
        ->withRole('Procurement Officer')
        ->create();

    expect($this->officer->can('view', $quotation))->toBeTrue()
        ->and($outsider->can('view', $quotation))->toBeFalse()
        ->and(SupplierQuotation::query()->visibleTo($this->officer)->whereKey($quotation->id)->exists())->toBeTrue()
        ->and(SupplierQuotation::query()->visibleTo($outsider)->whereKey($quotation->id)->exists())->toBeFalse();
});
