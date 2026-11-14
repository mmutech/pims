<?php

use App\Domain\Organization\Models\BusinessUnit;
use App\Domain\Procurement\Models\PurchaseRequisition;
use App\Domain\Procurement\Services\PurchaseRequisitionService;
use App\Enums\ApprovalStatus;
use App\Enums\PurchaseRequisitionStatus;
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
});

it('creates a draft requisition with a generated number and fixed-precision totals', function () {
    $requisition = $this->makeRequisition(
        $this->requester,
        $this->businessUnit,
        $this->department,
        $this->item,
        $this->unit,
    );

    expect($requisition->status)->toBe(PurchaseRequisitionStatus::Draft)
        ->and($requisition->document_number)->toStartWith('PR/')
        ->and($requisition->total_estimated_amount)->toBe('15000.00')
        ->and($requisition->approved_amount)->toBe('15000.00')
        ->and($requisition->items)->toHaveCount(1)
        ->and($requisition->items->first()->approved_quantity)->toBe('10.0000')
        ->and($requisition->items->first()->converted_quantity)->toBe('0.0000');
});

it('treats blank optional form values as null instead of zero', function () {
    $requisition = $this->makeRequisition(
        $this->requester,
        $this->businessUnit,
        $this->department,
        $this->item,
        $this->unit,
        [
            'cost_center_id' => '',
            'project_id' => '',
            'suggested_supplier_id' => '',
            'required_by' => '',
        ],
    );

    expect($requisition->cost_center_id)->toBeNull()
        ->and($requisition->project_id)->toBeNull()
        ->and($requisition->suggested_supplier_id)->toBeNull()
        ->and($requisition->required_by)->toBeNull();
});

it('rejects requisition lines with non-positive quantities', function () {
    expect(fn () => $this->makeRequisition(
        $this->requester,
        $this->businessUnit,
        $this->department,
        $this->item,
        $this->unit,
        [
            'items' => [[
                'item_id' => $this->item->id,
                'unit_of_measure_id' => $this->unit->id,
                'requested_quantity' => '0',
                'estimated_unit_price' => '1500.00',
            ]],
        ],
    ))->toThrow(DomainException::class, 'greater than zero');
});

it('submits a requisition and completes approval at the final stage', function () {
    $requisition = $this->makeRequisition(
        $this->requester,
        $this->businessUnit,
        $this->department,
        $this->item,
        $this->unit,
    );

    app(PurchaseRequisitionService::class)->submit($requisition, $this->requester);

    expect($requisition->fresh()->status)->toBe(PurchaseRequisitionStatus::Submitted)
        ->and($requisition->fresh()->approved_at)->toBeNull()
        ->and($this->approvalRequestFor($requisition)->status)->toBe(ApprovalStatus::Pending);

    $this->approveDocument($requisition, $this->manager);

    $requisition->refresh();

    expect($requisition->status)->toBe(PurchaseRequisitionStatus::Approved)
        ->and($requisition->approved_by)->toBe($this->manager->id)
        ->and($requisition->approved_at)->not->toBeNull()
        ->and($this->approvalRequestFor($requisition)->status)->toBe(ApprovalStatus::Approved);
});

it('marks a requisition partially approved when approved quantities are reduced', function () {
    $requisition = $this->makeRequisition(
        $this->requester,
        $this->businessUnit,
        $this->department,
        $this->item,
        $this->unit,
    );

    $line = $requisition->items()->first();

    app(PurchaseRequisitionService::class)->updateApprovedQuantities(
        $requisition,
        [$line->id => '6'],
        $this->manager,
    );

    app(PurchaseRequisitionService::class)->submit($requisition->fresh(), $this->requester);
    $this->approveDocument($requisition, $this->manager);

    $requisition->refresh();

    expect($requisition->status)->toBe(PurchaseRequisitionStatus::PartiallyApproved)
        ->and($requisition->approved_amount)->toBe('9000.00')
        ->and($requisition->items->first()->approved_quantity)->toBe('6.0000')
        ->and($requisition->items->first()->approved_quantity)->not->toBe('10.0000');
});

it('prevents approving more than the requested quantity', function () {
    $requisition = $this->makeRequisition(
        $this->requester,
        $this->businessUnit,
        $this->department,
        $this->item,
        $this->unit,
    );

    $line = $requisition->items()->first();

    expect(fn () => app(PurchaseRequisitionService::class)->updateApprovedQuantities(
        $requisition,
        [$line->id => '11'],
        $this->manager,
    ))->toThrow(DomainException::class, 'cannot exceed the requested quantity');
});

it('cancels a requisition and its pending approval request', function () {
    $requisition = $this->makeRequisition(
        $this->requester,
        $this->businessUnit,
        $this->department,
        $this->item,
        $this->unit,
    );

    app(PurchaseRequisitionService::class)->submit($requisition, $this->requester);

    $cancelled = app(PurchaseRequisitionService::class)->cancel(
        $requisition->fresh(),
        $this->requester,
        'Duplicate request raised by another department.',
    );

    expect($cancelled->status)->toBe(PurchaseRequisitionStatus::Cancelled)
        ->and($cancelled->cancellation_reason)->toContain('Duplicate request')
        ->and($cancelled->cancelled_at)->not->toBeNull()
        ->and($this->approvalRequestFor($cancelled)->status)->toBe(ApprovalStatus::Cancelled);
});

it('requires a reason before cancelling a requisition', function () {
    $requisition = $this->makeRequisition(
        $this->requester,
        $this->businessUnit,
        $this->department,
        $this->item,
        $this->unit,
    );

    expect(fn () => app(PurchaseRequisitionService::class)->cancel($requisition, $this->requester, '   '))
        ->toThrow(DomainException::class, 'cancellation reason');
});

it('blocks editing once a requisition has been submitted', function () {
    $requisition = $this->makeRequisition(
        $this->requester,
        $this->businessUnit,
        $this->department,
        $this->item,
        $this->unit,
    );

    app(PurchaseRequisitionService::class)->submit($requisition, $this->requester);

    expect(fn () => app(PurchaseRequisitionService::class)->update(
        $requisition->fresh(),
        $this->makeRequisitionData(
            $this->businessUnit,
            $this->department,
            $this->requester,
            $this->item,
            $this->unit,
            ['justification' => 'Attempted edit after submission.'],
        ),
        $this->requester,
    ))->toThrow(DomainException::class, 'Only draft or returned requisitions');
});

it('scopes requisitions to the requesting owner', function () {
    $outsider = User::factory()
        ->withRole('Department Requester')
        ->create();

    $requisition = $this->makeRequisition(
        $this->requester,
        $this->businessUnit,
        $this->department,
        $this->item,
        $this->unit,
    );

    expect($this->requester->can('view', $requisition))->toBeTrue()
        ->and($outsider->can('view', $requisition))->toBeFalse()
        ->and(PurchaseRequisition::query()->visibleTo($this->requester)->whereKey($requisition->id)->exists())->toBeTrue()
        ->and(PurchaseRequisition::query()->visibleTo($outsider)->whereKey($requisition->id)->exists())->toBeFalse();
});

it('enforces procurement permissions on requisitions', function () {
    $requisition = $this->makeRequisition(
        $this->requester,
        $this->businessUnit,
        $this->department,
        $this->item,
        $this->unit,
    );

    $auditor = User::factory()->withRole('Auditor')->create();
    $storeOfficer = User::factory()->withRole('Store Officer')->create();
    $officer = User::factory()
        ->withRole('Procurement Officer')
        ->create();

    expect($this->requester->can('create', PurchaseRequisition::class))->toBeTrue()
        ->and($this->requester->can('cancel', $requisition))->toBeFalse()
        ->and($auditor->can('create', PurchaseRequisition::class))->toBeFalse()
        ->and($auditor->can('view', $requisition))->toBeTrue()
        ->and($storeOfficer->can('viewAny', PurchaseRequisition::class))->toBeFalse()
        ->and($officer->can('cancel', $requisition))->toBeFalse();
});
