<?php

use App\Domain\Distribution\Models\MaterialRequisition;
use App\Domain\Distribution\Models\MaterialReturn;
use App\Domain\Distribution\Models\StockIssue;
use App\Domain\Distribution\Models\StockTransfer;
use App\Domain\Distribution\Services\MaterialRequisitionService;
use App\Enums\ReturnStatus;
use App\Livewire\Distribution\Issues\Form as IssueForm;
use App\Livewire\Distribution\Issues\Show as IssueShow;
use App\Livewire\Distribution\ProjectConsumption\Index as ProjectConsumptionIndex;
use App\Livewire\Distribution\Requisitions\Form as RequisitionForm;
use App\Livewire\Distribution\Returns\Form as ReturnForm;
use App\Livewire\Distribution\Returns\Show as ReturnShow;
use App\Livewire\Distribution\Transfers\Form as TransferForm;
use App\Livewire\Distribution\Transfers\Show as TransferShow;
use Livewire\Livewire;

it('renders every distribution index for a store officer', function () {
    $context = $this->makeDistributionContext();

    $this->actingAs($context['store_officer']);

    $this->get(route('distribution.requisitions.index'))->assertOk()->assertSee('Material Requisitions');
    $this->get(route('distribution.issues.index'))->assertOk()->assertSee('Stock Issues');
    $this->get(route('distribution.transfers.index'))->assertOk()->assertSee('Stock Transfers');
    $this->get(route('distribution.returns.index'))->assertOk()->assertSee('Material Returns');
    $this->get(route('distribution.project-consumption.index'))->assertOk()->assertSee('Project Consumption');
});

it('creates a material requisition through the form and renders its detail page', function () {
    $context = $this->makeDistributionContext();

    $this->actingAs($context['requester']);

    Livewire::test(RequisitionForm::class)
        ->assertOk()
        ->set('form.requesting_department_id', (string) $context['department']->id)
        ->set('form.destination_warehouse_id', (string) $context['warehouse']->id)
        ->set('form.purpose', 'UI smoke requisition for feeder maintenance.')
        ->set('form.priority', 'normal')
        ->set('items.0.item_id', (string) $context['item']->id)
        ->set('items.0.requested_quantity', '5.0000')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();

    $requisition = MaterialRequisition::query()->firstOrFail();

    expect($requisition->items)->toHaveCount(1);

    $this->get(route('distribution.requisitions.show', $requisition))
        ->assertOk()
        ->assertSee($requisition->document_number)
        ->assertSee('UI smoke requisition for feeder maintenance.');
});

it('issues reserved stock through the issue screen and acknowledges it as the recipient', function () {
    $context = $this->makeDistributionContext();

    $requisition = $this->submitAndApproveRequisition(
        $this->makeMaterialRequisition($context, '8.0000'),
        $context,
    );

    app(MaterialRequisitionService::class)->reserve($requisition, $context['store_officer']);

    $this->actingAs($context['store_officer']);

    Livewire::withQueryParams(['requisition' => $requisition->id])
        ->test(IssueForm::class)
        ->assertOk()
        ->assertSee('Reserved lines')
        ->set('form.issued_to', (string) $context['requester']->id)
        ->set('form.issued_by', (string) $context['store_officer']->id)
        ->set('lines.0.quantity', '5.0000')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();

    $issue = StockIssue::query()->firstOrFail();

    Livewire::test(IssueShow::class, ['issue' => $issue])
        ->call('post')
        ->assertHasNoErrors()
        ->assertSee('Posted');

    $issue->refresh();

    expect($issue->items->first()->issued_quantity)->toBe('5.0000');

    Livewire::actingAs($context['requester']);

    Livewire::test(IssueShow::class, ['issue' => $issue])
        ->set('showAcknowledgeModal', true)
        ->set('acknowledgementComment', 'Received at the district store.')
        ->call('acknowledge')
        ->assertHasNoErrors();

    expect($issue->refresh()->acknowledgement)->not->toBeNull();
});

it('dispatches and receives a transfer through the transfer screen including a damaged line', function () {
    $context = $this->makeDistributionContext();

    $this->actingAs($context['store_officer']);

    Livewire::test(TransferForm::class)
        ->assertOk()
        ->set('form.source_warehouse_id', (string) $context['warehouse']->id)
        ->set('form.destination_warehouse_id', (string) $context['destination_warehouse']->id)
        ->set('form.reason', 'UI smoke transfer between district stores.')
        ->set('items.0.item_id', (string) $context['item']->id)
        ->set('items.0.requested_quantity', '6.0000')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();

    $transfer = StockTransfer::query()->firstOrFail();

    Livewire::test(TransferShow::class, ['transfer' => $transfer])
        ->call('submit')
        ->assertHasNoErrors();

    $this->approveDocument($transfer, $context['warehouse_manager']);

    $transfer->refresh();

    Livewire::test(TransferShow::class, ['transfer' => $transfer])
        ->call('openDispatchModal')
        ->set('dispatchLines.0.quantity', '6.0000')
        ->call('postDispatch')
        ->assertHasNoErrors();

    $dispatch = $transfer->refresh()->dispatches()->firstOrFail();

    Livewire::actingAs($context['destination_officer']);

    Livewire::test(TransferShow::class, ['transfer' => $transfer->refresh()])
        ->call('openReceiveModal')
        ->set('receiveLines.0.received_quantity', '5.0000')
        ->set('receiveLines.0.damaged_quantity', '1.0000')
        ->call('receive')
        ->assertHasNoErrors();

    $transfer->refresh();

    expect($dispatch->refresh()->items()->firstOrFail()->received_quantity)->toBe('6.0000')
        ->and($transfer->discrepancies()->count())->toBe(1);
});

it('assesses and posts a material return through the return screens', function () {
    $context = $this->makeDistributionContext();

    $issue = $this->postedRequisitionIssue($context, '6.0000');

    $this->actingAs($context['requester']);

    Livewire::test(ReturnForm::class)
        ->assertOk()
        ->set('form.original_issue_id', (string) $issue->id)
        ->set('form.returned_by', (string) $context['requester']->id)
        ->set('form.reason', 'Unused cable returned from site.')
        ->set('items.0.returned_quantity', '3.0000')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();

    $return = MaterialReturn::query()->firstOrFail();

    Livewire::actingAs($context['quality_inspector']);

    Livewire::test(ReturnShow::class, ['materialReturn' => $return])
        ->call('openAssessmentModal')
        ->set('assessmentLines.0.condition', 'good')
        ->set('assessmentLines.0.disposition', 'return_to_stock')
        ->set('assessmentLines.0.accepted_quantity', '2.0000')
        ->set('assessmentLines.0.rejected_quantity', '1.0000')
        ->call('assess')
        ->assertHasNoErrors();

    Livewire::actingAs($context['store_officer']);

    Livewire::test(ReturnShow::class, ['materialReturn' => $return->refresh()])
        ->call('post')
        ->assertHasNoErrors();

    $return->refresh()->load('items');

    expect($return->status)->toBe(ReturnStatus::Posted)
        ->and($return->items->first()->accepted_quantity)->toBe('2.0000');
});

it('shows project consumption after material is issued to a project', function () {
    $context = $this->makeDistributionContext();
    $project = $this->makeProject($context);

    $requisition = $this->submitAndApproveRequisition(
        $this->makeMaterialRequisition($context, '4.0000', [
            'project_id' => $project->id,
            'cost_center_id' => null,
        ]),
        $context,
    );

    $this->reserveAndIssueRequisition($requisition, $context);

    $this->actingAs($context['store_officer']);

    Livewire::test(ProjectConsumptionIndex::class)
        ->assertOk()
        ->set('projectId', (string) $project->id)
        ->call('applyFilters')
        ->assertHasNoErrors()
        ->assertSee($context['item']->name)
        ->assertSee('Total issued value');
});
