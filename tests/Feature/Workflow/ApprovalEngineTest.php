<?php

use App\Domain\Workflow\Contracts\ApprovalEngine;
use App\Domain\Workflow\Models\ApprovalRequest;
use App\Domain\Workflow\Services\ApprovalDelegationService;
use App\Domain\Workflow\Services\ApprovalEscalationService;
use App\Domain\Workflow\Services\ApprovalFlowService;
use App\Enums\ApprovalActionType;
use App\Enums\ApprovalAssigneeType;
use App\Enums\ApprovalConditionOperator;
use App\Enums\ApprovalMode;
use App\Enums\ApprovalStatus;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;
use Tests\Fixtures\FakeApprovableDocument;

beforeEach(function () {
    $this->seed([PermissionSeeder::class, RoleSeeder::class]);
});

it('auto approves when no stages are applicable', function () {
    $actor = User::factory()->withRole('Department Requester')->create();
    $flow = app(ApprovalFlowService::class)->create([
        'name' => 'Test Flow',
        'document_type' => 'test_document',
        'is_active' => true,
        'stages' => [
            [
                'name' => 'High Value',
                'approval_mode' => ApprovalMode::AnyOne->value,
                'amount_min' => 500000,
                'amount_max' => null,
                'prevent_self_approval' => true,
                'assignments' => [
                    ['assignee_type' => ApprovalAssigneeType::Role->value, 'role_id' => Role::findByName('Procurement Manager')->id],
                ],
            ],
        ],
    ], $actor);

    $document = FakeApprovableDocument::makeDocument([
        'amount' => '10000.00',
        'requested_by' => $actor->id,
        'created_by' => $actor->id,
    ], 1, 'test_document');

    $request = app(ApprovalEngine::class)->submit($document, $actor);

    expect($request->status)->toBe(ApprovalStatus::Approved)
        ->and($request->active_key)->toBeNull()
        ->and(ApprovalRequest::query()->count())->toBe(1);
});

it('creates a pending request for applicable stages', function () {
    $requester = User::factory()->withRole('Department Requester')->create();
    $approver = User::factory()->withRole('Procurement Manager')->create();

    app(ApprovalFlowService::class)->create([
        'name' => 'PO Flow',
        'document_type' => 'test_document',
        'is_active' => true,
        'stages' => [
            [
                'name' => 'Review',
                'approval_mode' => ApprovalMode::AnyOne->value,
                'amount_min' => 100,
                'amount_max' => null,
                'prevent_self_approval' => true,
                'assignments' => [
                    ['assignee_type' => ApprovalAssigneeType::Role->value, 'role_id' => Role::findByName('Procurement Manager')->id],
                ],
            ],
        ],
    ], $requester);

    $document = FakeApprovableDocument::makeDocument([
        'amount' => '15000.00',
        'requested_by' => $requester->id,
        'created_by' => $requester->id,
    ], 1, 'test_document');

    $request = app(ApprovalEngine::class)->submit($document, $requester);

    expect($request->status)->toBe(ApprovalStatus::Pending)
        ->and($request->stages()->count())->toBe(1)
        ->and($request->stages()->first()->assignees()->count())->toBe(1)
        ->and($request->stages()->first()->assignees()->first()->user_id)->toBe($approver->id);
});

it('blocks self approval and requires a different approver', function () {
    $requester = User::factory()->withRole('Procurement Manager')->create();

    app(ApprovalFlowService::class)->create([
        'name' => 'Self Test',
        'document_type' => 'test_document',
        'is_active' => true,
        'stages' => [
            [
                'name' => 'Review',
                'approval_mode' => ApprovalMode::AnyOne->value,
                'prevent_self_approval' => true,
                'assignments' => [
                    ['assignee_type' => ApprovalAssigneeType::Role->value, 'role_id' => Role::findByName('Procurement Manager')->id],
                ],
            ],
        ],
    ], $requester);

    $document = FakeApprovableDocument::makeDocument([
        'amount' => '15000.00',
        'requested_by' => $requester->id,
        'created_by' => $requester->id,
    ], 1, 'test_document');

    $request = app(ApprovalEngine::class)->submit($document, $requester);

    expect($request->stages()->first()->assignees()->count())->toBe(0)
        ->and($request->stages()->first()->status->value)->toBe('blocked');
});

it('allows approval by assigned approver and advances the request', function () {
    $requester = User::factory()->withRole('Department Requester')->create();
    $approver = User::factory()->withRole('Procurement Manager')->create();

    app(ApprovalFlowService::class)->create([
        'name' => 'Flow',
        'document_type' => 'test_document',
        'is_active' => true,
        'stages' => [
            [
                'name' => 'Review',
                'approval_mode' => ApprovalMode::AnyOne->value,
                'assignments' => [
                    ['assignee_type' => ApprovalAssigneeType::Role->value, 'role_id' => Role::findByName('Procurement Manager')->id],
                ],
            ],
        ],
    ], $requester);

    $document = FakeApprovableDocument::makeDocument([
        'amount' => '15000.00',
        'requested_by' => $requester->id,
        'created_by' => $requester->id,
    ], 1, 'test_document');

    $request = app(ApprovalEngine::class)->submit($document, $requester);
    $updated = app(ApprovalEngine::class)->act($request, $approver, ApprovalActionType::Approve, 'OK');

    expect($updated->status)->toBe(ApprovalStatus::Approved)
        ->and($updated->stages()->first()->status->value)->toBe('approved');
});

it('rejects and prevents further actions', function () {
    $requester = User::factory()->withRole('Department Requester')->create();
    $approver = User::factory()->withRole('Procurement Manager')->create();

    app(ApprovalFlowService::class)->create([
        'name' => 'Flow',
        'document_type' => 'test_document',
        'is_active' => true,
        'stages' => [
            [
                'name' => 'Review',
                'approval_mode' => ApprovalMode::AnyOne->value,
                'assignments' => [
                    ['assignee_type' => ApprovalAssigneeType::Role->value, 'role_id' => Role::findByName('Procurement Manager')->id],
                ],
            ],
        ],
    ], $requester);

    $document = FakeApprovableDocument::makeDocument([
        'amount' => '15000.00',
        'requested_by' => $requester->id,
        'created_by' => $requester->id,
    ], 1, 'test_document');

    $request = app(ApprovalEngine::class)->submit($document, $requester);
    $updated = app(ApprovalEngine::class)->act($request, $approver, ApprovalActionType::Reject, 'Not suitable');

    expect($updated->status)->toBe(ApprovalStatus::Rejected);

    expect(fn () => app(ApprovalEngine::class)->act($updated, $approver, ApprovalActionType::Approve))
        ->toThrow(DomainException::class);
});

it('supports delegation', function () {
    $requester = User::factory()->withRole('Department Requester')->create();
    $delegator = User::factory()->withRole('Procurement Manager')->create();
    $delegate = User::factory()->withRole('Project Manager')->create();

    app(ApprovalFlowService::class)->create([
        'name' => 'Flow',
        'document_type' => 'test_document',
        'is_active' => true,
        'stages' => [
            [
                'name' => 'Review',
                'approval_mode' => ApprovalMode::AnyOne->value,
                'assignments' => [
                    ['assignee_type' => ApprovalAssigneeType::Role->value, 'role_id' => Role::findByName('Procurement Manager')->id],
                ],
            ],
        ],
    ], $requester);

    app(ApprovalDelegationService::class)->create(
        $delegator,
        $delegate,
        Carbon::now()->subHour(),
        Carbon::now()->addDay(),
        'test_document',
        'Cover',
        $delegator,
    );

    $document = FakeApprovableDocument::makeDocument([
        'amount' => '15000.00',
        'requested_by' => $requester->id,
        'created_by' => $requester->id,
    ], 1, 'test_document');

    $request = app(ApprovalEngine::class)->submit($document, $requester);
    $updated = app(ApprovalEngine::class)->act($request, $delegate, ApprovalActionType::Approve);

    expect($updated->status)->toBe(ApprovalStatus::Approved);
});

it('supports escalation when overdue', function () {
    $requester = User::factory()->withRole('Department Requester')->create();
    $approver = User::factory()->withRole('Procurement Manager')->create();
    $escalatee = User::factory()->withRole('Executive')->create();

    $flow = app(ApprovalFlowService::class)->create([
        'name' => 'Flow',
        'document_type' => 'test_document',
        'is_active' => true,
        'stages' => [
            [
                'name' => 'Review',
                'approval_mode' => ApprovalMode::AnyOne->value,
                'sla_hours' => 1,
                'assignments' => [
                    ['assignee_type' => ApprovalAssigneeType::Role->value, 'role_id' => Role::findByName('Procurement Manager')->id],
                ],
                'escalation_user_id' => $escalatee->id,
            ],
        ],
    ], $requester);

    $document = FakeApprovableDocument::makeDocument([
        'amount' => '15000.00',
        'requested_by' => $requester->id,
        'created_by' => $requester->id,
    ], 1, 'test_document');

    $request = app(ApprovalEngine::class)->submit($document, $requester);
    $stage = $request->stages()->first();
    $stage->forceFill([
        'sla_due_at' => Carbon::now()->subMinutes(30),
        'status' => 'in_progress',
    ])->save();

    $count = app(ApprovalEscalationService::class)->escalateOverdueStages();

    expect($count)->toBe(1)
        ->and($stage->fresh()->assignees()->where('user_id', $escalatee->id)->exists())->toBeTrue();
});

it('requires workflow.act to act and respects policies', function () {
    $requester = User::factory()->withRole('Department Requester')->create();
    $stranger = User::factory()->create();

    app(ApprovalFlowService::class)->create([
        'name' => 'Flow',
        'document_type' => 'test_document',
        'is_active' => true,
        'stages' => [
            [
                'name' => 'Review',
                'approval_mode' => ApprovalMode::AnyOne->value,
                'assignments' => [
                    ['assignee_type' => ApprovalAssigneeType::Role->value, 'role_id' => Role::findByName('Procurement Manager')->id],
                ],
            ],
        ],
    ], $requester);

    $document = FakeApprovableDocument::makeDocument([
        'amount' => '15000.00',
        'requested_by' => $requester->id,
        'created_by' => $requester->id,
    ], 1, 'test_document');

    $request = app(ApprovalEngine::class)->submit($document, $requester);

    expect(fn () => app(ApprovalEngine::class)->act($request, $stranger, ApprovalActionType::Approve))
        ->toThrow(AuthorizationException::class);
});

it('supports amount band and condition routing', function () {
    $requester = User::factory()->withRole('Department Requester')->create();
    $approver = User::factory()->withRole('Procurement Manager')->create();

    app(ApprovalFlowService::class)->create([
        'name' => 'Flow',
        'document_type' => 'test_document',
        'is_active' => true,
        'stages' => [
            [
                'name' => 'High',
                'approval_mode' => ApprovalMode::AnyOne->value,
                'amount_min' => 100000,
                'amount_max' => null,
                'assignments' => [
                    ['assignee_type' => ApprovalAssigneeType::Role->value, 'role_id' => Role::findByName('Procurement Manager')->id],
                ],
                'conditions' => [
                    ['field' => 'priority', 'operator' => ApprovalConditionOperator::Equals->value, 'value' => ['high'], 'logical_group' => 1],
                ],
            ],
        ],
    ], $requester);

    $document = FakeApprovableDocument::makeDocument([
        'amount' => '150000.00',
        'priority' => 'normal',
        'requested_by' => $requester->id,
        'created_by' => $requester->id,
    ], 1, 'test_document');

    $request = app(ApprovalEngine::class)->submit($document, $requester);

    expect($request->status)->toBe(ApprovalStatus::Approved);
});
