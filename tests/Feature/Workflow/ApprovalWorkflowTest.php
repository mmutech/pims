<?php

use App\Domain\Workflow\Contracts\ApprovalEngine;
use App\Domain\Workflow\Models\ApprovalRequest;
use App\Domain\Workflow\Services\ApprovalDelegationService;
use App\Domain\Workflow\Services\ApprovalExpiryService;
use App\Domain\Workflow\Services\ApprovalFlowService;
use App\Enums\ApprovalActionType;
use App\Enums\ApprovalAssigneeType;
use App\Enums\ApprovalMode;
use App\Enums\ApprovalStatus;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;
use Tests\Fixtures\FakeApprovableDocument;

beforeEach(function () {
    $this->seed([PermissionSeeder::class, RoleSeeder::class]);
});

function makeDocument(User $requester, array $overrides = []): FakeApprovableDocument
{
    return FakeApprovableDocument::makeDocument([
        'amount' => '15000.00',
        'requested_by' => $requester->id,
        'created_by' => $requester->id,
        ...$overrides,
    ], 1, 'test_document');
}

function makeFlow(User $actor, array $stages, string $type = 'test_document'): void
{
    app(ApprovalFlowService::class)->create([
        'name' => 'Test Flow',
        'document_type' => $type,
        'is_active' => true,
        'stages' => $stages,
    ], $actor);
}

it('requires every assignee to approve in all mode', function () {
    $requester = User::factory()->withRole('Department Requester')->create();
    $first = User::factory()->withRole('Procurement Manager')->create();
    $second = User::factory()->withRole('Procurement Manager')->create();

    makeFlow($requester, [[
        'name' => 'All Approvers',
        'approval_mode' => ApprovalMode::All->value,
        'assignments' => [
            ['assignee_type' => ApprovalAssigneeType::Role->value, 'role_id' => Role::findByName('Procurement Manager')->id],
        ],
    ]]);

    $request = app(ApprovalEngine::class)->submit(makeDocument($requester), $requester);

    $afterFirst = app(ApprovalEngine::class)->act($request, $first, ApprovalActionType::Approve);

    expect($afterFirst->status)->toBe(ApprovalStatus::Pending)
        ->and($afterFirst->stages()->first()->received_approvals)->toBe(1);

    expect(fn () => app(ApprovalEngine::class)->act($request, $first, ApprovalActionType::Approve))
        ->toThrow(DomainException::class, 'already acted');

    $afterSecond = app(ApprovalEngine::class)->act($request, $second, ApprovalActionType::Approve);

    expect($afterSecond->status)->toBe(ApprovalStatus::Approved);
});

it('advances through multiple stages in order', function () {
    $requester = User::factory()->withRole('Department Requester')->create();
    $manager = User::factory()->withRole('Procurement Manager')->create();
    $executive = User::factory()->withRole('Executive')->create();

    makeFlow($requester, [
        [
            'name' => 'Manager Review',
            'approval_mode' => ApprovalMode::AnyOne->value,
            'assignments' => [
                ['assignee_type' => ApprovalAssigneeType::Role->value, 'role_id' => Role::findByName('Procurement Manager')->id],
            ],
        ],
        [
            'name' => 'Executive Review',
            'approval_mode' => ApprovalMode::AnyOne->value,
            'assignments' => [
                ['assignee_type' => ApprovalAssigneeType::Role->value, 'role_id' => Role::findByName('Executive')->id],
            ],
        ],
    ]);

    $request = app(ApprovalEngine::class)->submit(makeDocument($requester), $requester);
    $afterFirst = app(ApprovalEngine::class)->act($request, $manager, ApprovalActionType::Approve);

    expect($afterFirst->status)->toBe(ApprovalStatus::Pending)
        ->and($afterFirst->current_stage_sequence)->toBe(2);

    $afterSecond = app(ApprovalEngine::class)->act($request, $executive, ApprovalActionType::Approve);

    expect($afterSecond->status)->toBe(ApprovalStatus::Approved);
});

it('returns a document for correction and allows resubmission', function () {
    $requester = User::factory()->withRole('Department Requester')->create();
    $approver = User::factory()->withRole('Procurement Manager')->create();

    makeFlow($requester, [[
        'name' => 'Review',
        'approval_mode' => ApprovalMode::AnyOne->value,
        'assignments' => [
            ['assignee_type' => ApprovalAssigneeType::Role->value, 'role_id' => Role::findByName('Procurement Manager')->id],
        ],
    ]]);

    $document = makeDocument($requester);
    $request = app(ApprovalEngine::class)->submit($document, $requester);
    $returned = app(ApprovalEngine::class)->act($request, $approver, ApprovalActionType::Return, 'Fix the amount');

    expect($returned->status)->toBe(ApprovalStatus::Returned)
        ->and($returned->active_key)->toBeNull();

    $resubmitted = app(ApprovalEngine::class)->submit($document, $requester);

    expect($resubmitted->id)->not->toBe($returned->id)
        ->and($resubmitted->status)->toBe(ApprovalStatus::Pending);
});

it('cancels a pending request by the requester', function () {
    $requester = User::factory()->withRole('Department Requester')->create();

    makeFlow($requester, [[
        'name' => 'Review',
        'approval_mode' => ApprovalMode::AnyOne->value,
        'assignments' => [
            ['assignee_type' => ApprovalAssigneeType::Role->value, 'role_id' => Role::findByName('Procurement Manager')->id],
        ],
    ]]);

    $request = app(ApprovalEngine::class)->submit(makeDocument($requester), $requester);
    $cancelled = app(ApprovalEngine::class)->cancel($request, $requester, 'No longer required');

    expect($cancelled->status)->toBe(ApprovalStatus::Cancelled)
        ->and($cancelled->active_key)->toBeNull()
        ->and($cancelled->actions()->where('action', ApprovalActionType::Cancel->value)->exists())->toBeTrue();
});

it('expires requests whose validity date has passed', function () {
    $requester = User::factory()->withRole('Department Requester')->create();

    makeFlow($requester, [[
        'name' => 'Review',
        'approval_mode' => ApprovalMode::AnyOne->value,
        'assignments' => [
            ['assignee_type' => ApprovalAssigneeType::Role->value, 'role_id' => Role::findByName('Procurement Manager')->id],
        ],
    ]]);

    $document = makeDocument($requester, ['expires_at' => Carbon::now()->subDay()]);
    app(ApprovalEngine::class)->submit($document, $requester);

    $count = app(ApprovalExpiryService::class)->expireRequests();

    expect($count)->toBe(1)
        ->and(ApprovalRequest::query()->first()->status)->toBe(ApprovalStatus::Expired);
});

it('lists pending approvals for assignees and delegates only', function () {
    $requester = User::factory()->withRole('Department Requester')->create();
    $approver = User::factory()->withRole('Procurement Manager')->create();
    $stranger = User::factory()->create();

    makeFlow($requester, [[
        'name' => 'Review',
        'approval_mode' => ApprovalMode::AnyOne->value,
        'assignments' => [
            ['assignee_type' => ApprovalAssigneeType::Role->value, 'role_id' => Role::findByName('Procurement Manager')->id],
        ],
    ]]);

    app(ApprovalEngine::class)->submit(makeDocument($requester), $requester);

    expect(app(ApprovalEngine::class)->pendingFor($approver))->toHaveCount(1)
        ->and(app(ApprovalEngine::class)->pendingFor($stranger))->toHaveCount(0);
});

it('marks stages blocked when no approver can be resolved', function () {
    $requester = User::factory()->withRole('Department Requester')->create();

    makeFlow($requester, [[
        'name' => 'Unsupported',
        'approval_mode' => ApprovalMode::AnyOne->value,
        'assignments' => [
            ['assignee_type' => ApprovalAssigneeType::DepartmentManager->value],
        ],
    ]]);

    $request = app(ApprovalEngine::class)->submit(makeDocument($requester), $requester);
    $stage = $request->stages()->first();

    expect($request->status)->toBe(ApprovalStatus::Pending)
        ->and($stage->status->value)->toBe('blocked')
        ->and($stage->blocked_reason)->toContain('No eligible approvers');
});

it('allows an override holder to approve a blocked stage and clears the blocked reason', function () {
    $requester = User::factory()->withRole('Department Requester')->create();
    $executive = User::factory()->withRole('Executive')->create();

    makeFlow($requester, [[
        'name' => 'Unsupported',
        'approval_mode' => ApprovalMode::AnyOne->value,
        'assignments' => [
            ['assignee_type' => ApprovalAssigneeType::DepartmentManager->value],
        ],
    ]]);

    $request = app(ApprovalEngine::class)->submit(makeDocument($requester), $requester);
    $request = app(ApprovalEngine::class)->act($request, $executive, ApprovalActionType::Approve);

    expect($request->status)->toBe(ApprovalStatus::Approved)
        ->and($request->stages()->first()->blocked_reason)->toBeNull();
});

it('blocks the last editor from approving when segregation of duties is enabled', function () {
    $creator = User::factory()->withRole('Department Requester')->create();
    $editor = User::factory()->withRole('Procurement Manager')->create();

    makeFlow($creator, [[
        'name' => 'Review',
        'approval_mode' => ApprovalMode::AnyOne->value,
        'prevent_self_approval' => true,
        'assignments' => [
            ['assignee_type' => ApprovalAssigneeType::Role->value, 'role_id' => Role::findByName('Procurement Manager')->id],
        ],
    ]]);

    $document = FakeApprovableDocument::makeDocument([
        'amount' => '15000.00',
        'requested_by' => $creator->id,
        'created_by' => $creator->id,
        'updated_by' => $editor->id,
    ], 1, 'test_document');

    $request = app(ApprovalEngine::class)->submit($document, $creator);

    expect($request->stages()->first()->assignees()->count())->toBe(0);
});

it('allows the delegate to see and act on pending approvals', function () {
    $requester = User::factory()->withRole('Department Requester')->create();
    $delegator = User::factory()->withRole('Procurement Manager')->create();
    $delegate = User::factory()->withRole('Project Manager')->create();

    makeFlow($requester, [[
        'name' => 'Review',
        'approval_mode' => ApprovalMode::AnyOne->value,
        'assignments' => [
            ['assignee_type' => ApprovalAssigneeType::Role->value, 'role_id' => Role::findByName('Procurement Manager')->id],
        ],
    ]]);

    app(ApprovalDelegationService::class)->create(
        $delegator,
        $delegate,
        Carbon::now()->subHour(),
        Carbon::now()->addDay(),
        null,
        null,
        $delegator,
    );

    $request = app(ApprovalEngine::class)->submit(makeDocument($requester), $requester);

    expect(app(ApprovalEngine::class)->pendingFor($delegate))->toHaveCount(1)
        ->and($delegate->can('act', $request))->toBeTrue()
        ->and(app(ApprovalEngine::class)->act($request, $delegate, ApprovalActionType::Approve)->status)->toBe(ApprovalStatus::Approved);
});
