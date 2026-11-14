<?php

use App\Domain\Workflow\Models\ApprovalDelegation;
use App\Domain\Workflow\Models\ApprovalFlow;
use App\Domain\Workflow\Models\ApprovalRequest;
use App\Domain\Workflow\Services\ApprovalDelegationService;
use App\Domain\Workflow\Services\ApprovalEngineService;
use App\Domain\Workflow\Services\ApprovalFlowService;
use App\Enums\ApprovalAssigneeType;
use App\Enums\ApprovalMode;
use App\Livewire\Approvals\History;
use App\Livewire\Approvals\Index as ApprovalsIndex;
use App\Livewire\Delegations\Index as DelegationsIndex;
use App\Livewire\Workflows\Form as WorkflowsForm;
use App\Livewire\Workflows\Index as WorkflowsIndex;
use App\Models\User;
use App\Support\Permissions;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\Fixtures\FakeApprovableDocument;

beforeEach(function () {
    $this->seed([PermissionSeeder::class, RoleSeeder::class]);
});

function createTestFlow(User $actor, string $documentType = 'test_document'): ApprovalFlow
{
    return app(ApprovalFlowService::class)->create([
        'name' => 'Test Workflow',
        'document_type' => $documentType,
        'description' => 'Test',
        'is_active' => true,
        'stages' => [[
            'name' => 'Review',
            'approval_mode' => ApprovalMode::AnyOne->value,
            'required_approvals' => 1,
            'prevent_self_approval' => true,
            'is_final' => true,
            'assignments' => [[
                'assignee_type' => ApprovalAssigneeType::Role->value,
                'role_id' => Role::findByName('Procurement Manager')->id,
                'is_primary' => true,
            ]],
            'conditions' => [],
        ]],
    ], $actor);
}

it('shows pending approvals for authorized users', function () {
    $requester = User::factory()->withRole('Department Requester')->create();
    $approver = User::factory()->withRole('Procurement Manager')->create();

    $flow = createTestFlow($requester);
    $document = FakeApprovableDocument::makeDocument([
        'amount' => '1000.00',
        'requested_by' => $requester->id,
        'created_by' => $requester->id,
    ], 1, 'test_document');

    app(ApprovalEngineService::class)->submit($document, $requester);

    $this->actingAs($approver);

    Livewire::test(ApprovalsIndex::class)
        ->assertStatus(200)
        ->assertSee('My Approvals');
});

it('shows the approval detail screen with stage and escalation history', function () {
    $requester = User::factory()->withRole('Department Requester')->create();
    $approver = User::factory()->withRole('Procurement Manager')->create();

    createTestFlow($requester);
    $document = FakeApprovableDocument::makeDocument([
        'amount' => '1000.00',
        'requested_by' => $requester->id,
        'created_by' => $requester->id,
    ], 1, 'test_document');

    app(ApprovalEngineService::class)->submit($document, $requester);
    $request = ApprovalRequest::query()->firstOrFail();

    $this->actingAs($approver)
        ->get(route('approvals.show', $request))
        ->assertOk()
        ->assertSee('Review')
        ->assertSee('Approval routing');
});

it('only allows assignees of the current stage to act', function () {
    $requester = User::factory()->withRole('Department Requester')->create();
    $firstApprover = User::factory()->withRole('Procurement Officer')->create();
    $secondApprover = User::factory()->withRole('Procurement Manager')->create();

    app(ApprovalFlowService::class)->create([
        'name' => 'Two stage flow',
        'document_type' => 'test_document',
        'description' => 'Test',
        'is_active' => true,
        'stages' => [
            [
                'name' => 'Review',
                'approval_mode' => ApprovalMode::AnyOne->value,
                'required_approvals' => 1,
                'prevent_self_approval' => true,
                'is_final' => false,
                'assignments' => [[
                    'assignee_type' => ApprovalAssigneeType::Role->value,
                    'role_id' => Role::findByName('Procurement Officer')->id,
                    'is_primary' => true,
                ]],
                'conditions' => [],
            ],
            [
                'name' => 'Final approval',
                'approval_mode' => ApprovalMode::AnyOne->value,
                'required_approvals' => 1,
                'prevent_self_approval' => true,
                'is_final' => true,
                'assignments' => [[
                    'assignee_type' => ApprovalAssigneeType::Role->value,
                    'role_id' => Role::findByName('Procurement Manager')->id,
                    'is_primary' => true,
                ]],
                'conditions' => [],
            ],
        ],
    ], $requester);

    $document = FakeApprovableDocument::makeDocument([
        'amount' => '1000.00',
        'requested_by' => $requester->id,
        'created_by' => $requester->id,
    ], 1, 'test_document');

    app(ApprovalEngineService::class)->submit($document, $requester);
    $request = ApprovalRequest::query()->firstOrFail();

    expect($firstApprover->can('act', $request))->toBeTrue()
        ->and($secondApprover->can('act', $request))->toBeFalse();
});

it('grants procurement officers permission to act on approval stages', function () {
    $officer = User::factory()->withRole('Procurement Officer')->create();

    expect($officer->can(Permissions::WORKFLOW_ACT))->toBeTrue();
});

it('prevents unauthorized users from viewing approvals index', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test(ApprovalsIndex::class)->assertForbidden();
});

it('shows approval history with filters', function () {
    $requester = User::factory()->withRole('Department Requester')->create();
    $approver = User::factory()->withRole('Procurement Manager')->create();

    createTestFlow($requester);
    $document = FakeApprovableDocument::makeDocument([
        'amount' => '1000.00',
        'requested_by' => $requester->id,
        'created_by' => $requester->id,
    ], 1, 'test_document');

    app(ApprovalEngineService::class)->submit($document, $requester);

    $this->actingAs($requester);

    Livewire::test(History::class)
        ->assertStatus(200)
        ->assertSee('Approval History');
});

it('allows workflow admins to view workflows index', function () {
    $user = User::factory()->withRole('System Administrator')->create();

    $this->actingAs($user);

    Livewire::test(WorkflowsIndex::class)
        ->assertStatus(200)
        ->assertSee('Approval Workflows');
});

it('prevents non-admins from viewing workflows index', function () {
    $user = User::factory()->withRole('Department Requester')->create();

    $this->actingAs($user);

    Livewire::test(WorkflowsIndex::class)->assertForbidden();
});

it('creates a workflow through the form', function () {
    $user = User::factory()->withRole('System Administrator')->create();
    $role = Role::findByName('Procurement Manager');

    $this->actingAs($user);

    Livewire::test(WorkflowsForm::class)
        ->set('form.name', 'Purchase Request Workflow')
        ->set('form.document_type', 'purchase_request')
        ->set('form.is_active', true)
        ->set('form.stages.0.name', 'Manager Review')
        ->set('form.stages.0.approval_mode', ApprovalMode::AnyOne->value)
        ->set('form.stages.0.required_approvals', 1)
        ->set('form.stages.0.prevent_self_approval', true)
        ->set('form.stages.0.is_final', true)
        ->set('form.stages.0.assignments.0.assignee_type', ApprovalAssigneeType::Role->value)
        ->set('form.stages.0.assignments.0.role_id', $role->id)
        ->call('save')
        ->assertHasNoErrors();

    expect(ApprovalFlow::where('document_type', 'purchase_request')->exists())->toBeTrue();
});

it('validates workflow form requires stages and assignees', function () {
    $user = User::factory()->withRole('System Administrator')->create();

    $this->actingAs($user);

    Livewire::test(WorkflowsForm::class)
        ->set('form.name', 'Bad Workflow')
        ->set('form.document_type', 'bad_workflow')
        ->set('form.stages.0.assignments', [])
        ->call('save')
        ->assertHasErrors();
});

it('allows delegation creation by users with permission', function () {
    $delegator = User::factory()->withRole('Procurement Manager')->create();
    $delegate = User::factory()->withRole('Project Manager')->create();

    $this->actingAs($delegator);

    Livewire::test(DelegationsIndex::class)
        ->call('openCreate')
        ->set('delegateId', $delegate->id)
        ->set('documentType', '')
        ->set('startsAt', now()->format('Y-m-d'))
        ->set('endsAt', now()->addDays(3)->format('Y-m-d'))
        ->set('reason', 'Coverage')
        ->call('save')
        ->assertHasNoErrors();

    expect(ApprovalDelegation::where('delegator_id', $delegator->id)->where('delegate_id', $delegate->id)->exists())->toBeTrue();
});

it('prevents delegating to self', function () {
    $user = User::factory()->withRole('Procurement Manager')->create();

    $this->actingAs($user);

    Livewire::test(DelegationsIndex::class)
        ->call('openCreate')
        ->set('delegateId', $user->id)
        ->set('startsAt', now()->format('Y-m-d'))
        ->set('endsAt', now()->addDays(1)->format('Y-m-d'))
        ->call('save')
        ->assertHasErrors(['delegateId']);
});

it('cancels delegation by delegator', function () {
    $delegator = User::factory()->withRole('Procurement Manager')->create();
    $delegate = User::factory()->withRole('Project Manager')->create();

    $delegation = app(ApprovalDelegationService::class)->create(
        $delegator,
        $delegate,
        Carbon::now()->subHour(),
        Carbon::now()->addDay(),
        null,
        'Test',
        $delegator,
    );

    $this->actingAs($delegator);

    Livewire::test(DelegationsIndex::class)->call('cancelDelegation', $delegation->id)->assertHasNoErrors();

    expect($delegation->fresh()->is_active)->toBeFalse();
});
