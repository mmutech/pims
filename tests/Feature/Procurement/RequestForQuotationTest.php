<?php

use App\Domain\Organization\Models\BusinessUnit;
use App\Domain\Procurement\Models\RequestForQuotation;
use App\Domain\Procurement\Services\PurchaseRequisitionService;
use App\Domain\Procurement\Services\RequestForQuotationService;
use App\Domain\Supplier\Models\Supplier;
use App\DTOs\Procurement\RfqData;
use App\Enums\RequestForQuotationStatus;
use App\Models\User;

beforeEach(function () {
    $this->seedProcurement();
    $this->makeProcurementFlow('purchase_requisition');

    $this->businessUnit = BusinessUnit::factory()->create();
    $this->department = $this->makeDepartment();
    $this->requester = User::factory()
        ->withRole('Department Requester')
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

    $this->requisition = $requisition->refresh();
});

it('creates an issued RFQ from an approved requisition and reserves the sourced quantity', function () {
    $rfq = $this->makeIssuedRfq(
        $this->requisition,
        $this->requester,
        [$this->supplierA, $this->supplierB],
    );

    $line = $this->requisition->items()->first();

    expect($rfq->status)->toBe(RequestForQuotationStatus::Issued)
        ->and($rfq->document_number)->toStartWith('RFQ/')
        ->and($rfq->issued_at)->not->toBeNull()
        ->and($rfq->items)->toHaveCount(1)
        ->and($rfq->suppliers)->toHaveCount(2)
        ->and($line->converted_quantity)->toBe('10.0000')
        ->and($line->remainingQuantity())->toBe('0.0000');
});

it('refuses to source an unapproved requisition', function () {
    $draft = $this->makeRequisition(
        $this->requester,
        $this->businessUnit,
        $this->department,
        $this->item,
        $this->unit,
    );

    expect(fn () => app(RequestForQuotationService::class)->createFromRequisition(
        $draft,
        RfqData::fromArray([
            'business_unit_id' => $this->businessUnit->id,
            'title' => 'Unauthorised sourcing',
            'closing_date' => now()->addDays(10)->toDateString(),
            'supplier_ids' => [$this->supplierA->id],
        ]),
        $this->requester,
    ))->toThrow(DomainException::class, 'Only approved requisitions can be sourced');
});

it('prevents the RFQ from converting more than the approved quantity', function () {
    $line = $this->requisition->items()->first();

    expect(fn () => app(RequestForQuotationService::class)->createFromRequisition(
        $this->requisition,
        RfqData::fromArray([
            'business_unit_id' => $this->businessUnit->id,
            'title' => 'Over-conversion attempt',
            'closing_date' => now()->addDays(10)->toDateString(),
            'supplier_ids' => [$this->supplierA->id],
            'items' => [[
                'purchase_requisition_item_id' => $line->id,
                'quantity' => '11',
            ]],
        ]),
        $this->requester,
    ))->toThrow(DomainException::class, 'cannot exceed the approved requisition balance');
});

it('releases reserved quantities when an RFQ is cancelled', function () {
    $rfq = app(RequestForQuotationService::class)->createFromRequisition(
        $this->requisition,
        RfqData::fromArray([
            'business_unit_id' => $this->businessUnit->id,
            'title' => 'Cancelled sourcing round',
            'closing_date' => now()->addDays(10)->toDateString(),
            'supplier_ids' => [$this->supplierA->id],
        ]),
        $this->requester,
    );

    expect($this->requisition->items()->first()->converted_quantity)->toBe('10.0000');

    $cancelled = app(RequestForQuotationService::class)->cancel($rfq, $this->requester);

    expect($cancelled->status)->toBe(RequestForQuotationStatus::Cancelled)
        ->and($cancelled->cancelled_at)->not->toBeNull()
        ->and($this->requisition->items()->first()->converted_quantity)->toBe('0.0000')
        ->and($this->requisition->items()->first()->remainingQuantity())->toBe('10.0000');
});

it('requires at least one invited supplier before issuing an RFQ', function () {
    $rfq = app(RequestForQuotationService::class)->createFromRequisition(
        $this->requisition,
        RfqData::fromArray([
            'business_unit_id' => $this->businessUnit->id,
            'title' => 'No suppliers invited',
            'closing_date' => now()->addDays(10)->toDateString(),
            'supplier_ids' => [],
        ]),
        $this->requester,
    );

    expect(fn () => app(RequestForQuotationService::class)->issue($rfq, $this->requester))
        ->toThrow(DomainException::class, 'Invite at least one supplier');
});

it('closes an issued RFQ to new quotations', function () {
    $rfq = $this->makeIssuedRfq($this->requisition, $this->requester, [$this->supplierA]);

    $closed = app(RequestForQuotationService::class)->close($rfq, $this->requester);

    expect($closed->status)->toBe(RequestForQuotationStatus::Closed)
        ->and($closed->closed_at)->not->toBeNull();
});

it('scopes RFQs to the creator and enforces view permissions', function () {
    $rfq = $this->makeIssuedRfq($this->requisition, $this->requester, [$this->supplierA]);

    $outsider = User::factory()
        ->withRole('Procurement Officer')
        ->create();
    $officer = User::factory()
        ->withRole('Procurement Officer')
        ->create();
    $storeOfficer = User::factory()->withRole('Store Officer')->create();

    expect($this->requester->can('view', $rfq))->toBeTrue()
        ->and($officer->can('view', $rfq))->toBeFalse()
        ->and($outsider->can('view', $rfq))->toBeFalse()
        ->and($storeOfficer->can('viewAny', RequestForQuotation::class))->toBeFalse()
        ->and(RequestForQuotation::query()->visibleTo($this->requester)->whereKey($rfq->id)->exists())->toBeTrue()
        ->and(RequestForQuotation::query()->visibleTo($outsider)->whereKey($rfq->id)->exists())->toBeFalse();
});
