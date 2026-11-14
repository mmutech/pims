<?php

namespace App\Domain\Workflow\Services;

use App\Domain\Workflow\Contracts\Approvable;
use App\Domain\Workflow\Contracts\ApprovalEngine;
use App\Domain\Workflow\Events\ApprovalCancelled;
use App\Domain\Workflow\Events\ApprovalExpired;
use App\Domain\Workflow\Events\ApprovalRequested;
use App\Domain\Workflow\Events\ApprovalStageCompleted;
use App\Domain\Workflow\Events\DocumentApproved;
use App\Domain\Workflow\Events\DocumentRejected;
use App\Domain\Workflow\Events\DocumentReturned;
use App\Domain\Workflow\Models\ApprovalAction;
use App\Domain\Workflow\Models\ApprovalDelegation;
use App\Domain\Workflow\Models\ApprovalFlow;
use App\Domain\Workflow\Models\ApprovalRequest;
use App\Domain\Workflow\Models\ApprovalRequestStage;
use App\Domain\Workflow\Models\ApprovalStage;
use App\Domain\Workflow\Models\ApprovalStageAssignee;
use App\Enums\ApprovalActionType;
use App\Enums\ApprovalMode;
use App\Enums\ApprovalRequestStageStatus;
use App\Enums\ApprovalStageAssigneeStatus;
use App\Enums\ApprovalStatus;
use App\Models\User;
use App\Support\Permissions;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class ApprovalEngineService implements ApprovalEngine
{
    public function __construct(
        private readonly ApprovalConditionEvaluator $conditions,
        private readonly ApprovalAssignmentResolver $assignments,
        private readonly ApprovalNotifier $notifier,
    ) {}

    public function submit(Model&Approvable $document, User $actor): ApprovalRequest
    {
        return DB::transaction(function () use ($document, $actor): ApprovalRequest {
            $documentType = $document->approvalDocumentType();
            $activeKey = $documentType.':'.$document->getKey();

            if (ApprovalRequest::query()->where('active_key', $activeKey)->lockForUpdate()->exists()) {
                throw new DomainException('An approval request is already pending for this document.');
            }

            $flow = ApprovalFlow::query()
                ->active()
                ->where('document_type', $documentType)
                ->with([
                    'stages.conditions',
                    'stages.assignments.role',
                    'stages.assignments.user',
                ])
                ->first();

            if ($flow === null) {
                throw new DomainException("No active approval flow is configured for [{$documentType}].");
            }

            if ($flow->stages->isEmpty()) {
                throw new DomainException("The approval flow for [{$documentType}] has no stages.");
            }

            $attributes = $document->approvalAttributes();
            $attributes['requested_by'] ??= $actor->id;
            $attributes['created_by'] ??= $actor->id;

            $amount = isset($attributes['amount']) && $attributes['amount'] !== null
                ? (string) $attributes['amount']
                : null;

            $applicable = [];

            foreach ($flow->stages as $stage) {
                if (! $stage->appliesToAmount($amount)) {
                    continue;
                }

                if (! $this->conditions->evaluate($stage->conditions, $attributes)) {
                    continue;
                }

                $excluded = $stage->prevent_self_approval
                    ? $this->selfApprovalExclusions($attributes)
                    : [];

                $assignees = $this->assignments->resolve($stage->assignments, $attributes, $excluded);

                $applicable[] = ['stage' => $stage, 'assignees' => $assignees];
            }

            $request = ApprovalRequest::create([
                'document_type' => $documentType,
                'document_id' => $document->getKey(),
                'approval_flow_id' => $flow->id,
                'status' => ApprovalStatus::Pending,
                'current_stage_sequence' => null,
                'requested_by' => (int) $attributes['requested_by'],
                'requested_at' => now(),
                'expires_at' => isset($attributes['expires_at'])
                    ? Carbon::parse($attributes['expires_at'])
                    : null,
                'active_key' => $activeKey,
                'metadata' => [
                    'document_number' => $attributes['document_number'] ?? null,
                    'amount' => $amount,
                    'currency' => $attributes['currency'] ?? null,
                    'created_by' => $attributes['created_by'] ?? null,
                    'updated_by' => $attributes['updated_by'] ?? null,
                    'requested_by' => $attributes['requested_by'] ?? null,
                    'department_id' => $attributes['department_id'] ?? null,
                    'project_id' => $attributes['project_id'] ?? null,
                    'warehouse_id' => $attributes['warehouse_id'] ?? null,
                ],
            ]);

            foreach ($applicable as $row) {
                /** @var ApprovalStage $stage */
                $stage = $row['stage'];
                $assignees = $row['assignees'];

                $mode = $stage->approval_mode;
                $required = $mode === ApprovalMode::AnyOne ? 1 : max(1, $assignees->count());

                $requestStage = $request->stages()->create([
                    'approval_stage_id' => $stage->id,
                    'sequence' => $stage->sequence,
                    'name' => $stage->name,
                    'approval_mode' => $mode->value,
                    'prevent_self_approval' => $stage->prevent_self_approval,
                    'is_final' => $stage->is_final,
                    'status' => ApprovalRequestStageStatus::Pending,
                    'required_approvals' => $required,
                    'received_approvals' => 0,
                ]);

                foreach ($assignees as $assignee) {
                    $requestStage->assignees()->create([
                        'user_id' => $assignee->id,
                        'status' => ApprovalStageAssigneeStatus::Pending,
                        'is_delegated' => false,
                        'delegated_from_id' => null,
                    ]);
                }
            }

            if ($applicable === []) {
                $this->autoApprove($request, $document);

                return $request->refresh();
            }

            $first = $request->stages()->orderBy('sequence')->firstOrFail();
            $this->activateStage($request, $first);

            if ($first->status === ApprovalRequestStageStatus::InProgress) {
                $this->notifier->assigned($request->refresh(), $first->refresh());
            }

            $document->onApprovalStatusChanged($request->refresh());

            event(new ApprovalRequested($request));

            return $request->refresh()->load('stages.assignees');
        });
    }

    public function act(
        ApprovalRequest $request,
        User $actor,
        ApprovalActionType $action,
        ?string $comment = null,
        array $metadata = [],
    ): ApprovalRequest {
        if (! in_array($action, [ApprovalActionType::Approve, ApprovalActionType::Reject, ApprovalActionType::Return], true)) {
            throw new DomainException("Action [{$action->value}] must be performed through its dedicated service.");
        }

        return DB::transaction(function () use ($request, $actor, $action, $comment, $metadata): ApprovalRequest {
            if (! $actor->can(Permissions::WORKFLOW_ACT)) {
                throw new AuthorizationException('You are not permitted to act on approvals.');
            }

            $request = ApprovalRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();

            if (! $request->isPending()) {
                throw new DomainException('This approval request is no longer pending.');
            }

            $stage = ApprovalRequestStage::query()
                ->where('approval_request_id', $request->id)
                ->where('sequence', $request->current_stage_sequence)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($stage->status, [ApprovalRequestStageStatus::Pending, ApprovalRequestStageStatus::InProgress, ApprovalRequestStageStatus::Blocked], true)) {
                throw new DomainException('The current approval stage is already complete.');
            }

            $isOverride = false;

            $assignee = $this->findEffectiveAssignee($stage, $actor, $request->document_type);

            if ($assignee === null) {
                if ($action !== ApprovalActionType::Approve || ! $actor->can(Permissions::WORKFLOW_OVERRIDE)) {
                    throw new AuthorizationException('You are not an approver for the current stage.');
                }

                $isOverride = true;
            }

            if (! $isOverride && $assignee !== null && $assignee->status !== ApprovalStageAssigneeStatus::Pending) {
                throw new DomainException('You have already acted on this approval stage.');
            }

            if ($assignee !== null && $stage->prevent_self_approval && $this->isSelfApproval($request, $actor)) {
                throw new DomainException('Segregation of duties: you cannot approve a document you created or last edited.');
            }

            $actionMetadata = $metadata;

            if ($assignee !== null && $assignee->user_id !== $actor->id) {
                $actionMetadata['delegated_from'] = $assignee->user_id;
            }

            if ($isOverride) {
                $actionMetadata['override'] = true;
            }

            $this->recordAction($request, $stage, $actor, $action, $comment, $actionMetadata);

            if ($action === ApprovalActionType::Approve) {
                if ($isOverride) {
                    $stage->assignees()->where('status', ApprovalStageAssigneeStatus::Pending->value)
                        ->update(['status' => ApprovalStageAssigneeStatus::Skipped->value, 'acted_at' => now()]);

                    $stage->forceFill([
                        'received_approvals' => $stage->required_approvals,
                    ])->save();
                } else {
                    $assignee->forceFill([
                        'status' => ApprovalStageAssigneeStatus::Approved,
                        'acted_at' => now(),
                    ])->save();

                    $stage->increment('received_approvals');
                }

                if ($stage->refresh()->isSatisfied()) {
                    $stage->assignees()->where('status', ApprovalStageAssigneeStatus::Pending->value)
                        ->update(['status' => ApprovalStageAssigneeStatus::Skipped->value, 'acted_at' => now()]);

                    $this->completeStage($request, $stage);
                }
            }

            if ($action === ApprovalActionType::Reject) {
                $assignee->forceFill([
                    'status' => ApprovalStageAssigneeStatus::Rejected,
                    'acted_at' => now(),
                ])->save();

                $stage->assignees()->where('status', ApprovalStageAssigneeStatus::Pending->value)
                    ->update(['status' => ApprovalStageAssigneeStatus::Skipped->value, 'acted_at' => now()]);

                $stage->forceFill([
                    'status' => ApprovalRequestStageStatus::Rejected,
                    'completed_at' => now(),
                ])->save();

                $this->finaliseRequest($request, ApprovalStatus::Rejected, $comment);
                event(new DocumentRejected($request->refresh()));
            }

            if ($action === ApprovalActionType::Return) {
                $assignee->forceFill([
                    'status' => ApprovalStageAssigneeStatus::Rejected,
                    'acted_at' => now(),
                ])->save();

                $stage->assignees()->where('status', ApprovalStageAssigneeStatus::Pending->value)
                    ->update(['status' => ApprovalStageAssigneeStatus::Skipped->value, 'acted_at' => now()]);

                $stage->forceFill([
                    'status' => ApprovalRequestStageStatus::Skipped,
                    'completed_at' => now(),
                ])->save();

                $this->finaliseRequest($request, ApprovalStatus::Returned, $comment);
                event(new DocumentReturned($request->refresh()));
            }

            if ($request->refresh()->isPending()) {
                $request->load('stages.assignees');
            }

            $this->syncDocument($request->refresh());

            return $request->refresh()->load('stages.assignees');
        });
    }

    public function cancel(ApprovalRequest $request, User $actor, string $reason): ApprovalRequest
    {
        if (blank($reason)) {
            throw new DomainException('A cancellation reason is required.');
        }

        return DB::transaction(function () use ($request, $actor, $reason): ApprovalRequest {
            $request = ApprovalRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();

            if (! $request->isPending()) {
                throw new DomainException('Only pending approval requests can be cancelled.');
            }

            if ($request->requested_by !== $actor->id && ! $actor->can(Permissions::WORKFLOW_OVERRIDE)) {
                throw new AuthorizationException('Only the requester or a workflow administrator can cancel this request.');
            }

            $stage = ApprovalRequestStage::query()
                ->where('approval_request_id', $request->id)
                ->where('sequence', $request->current_stage_sequence)
                ->lockForUpdate()
                ->first();

            $this->recordAction($request, $stage, $actor, ApprovalActionType::Cancel, $reason);

            $this->skipOpenStages($request, $stage);
            $this->finaliseRequest($request, ApprovalStatus::Cancelled, $reason);
            $this->syncDocument($request->refresh());

            event(new ApprovalCancelled($request->refresh()));

            return $request->refresh()->load('stages.assignees');
        });
    }

    public function expire(ApprovalRequest $request, string $reason = 'Document validity expired'): ApprovalRequest
    {
        return DB::transaction(function () use ($request, $reason): ApprovalRequest {
            $request = ApprovalRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();

            if (! $request->isPending()) {
                return $request->refresh();
            }

            $stage = ApprovalRequestStage::query()
                ->where('approval_request_id', $request->id)
                ->where('sequence', $request->current_stage_sequence)
                ->lockForUpdate()
                ->first();

            $this->recordAction(
                $request,
                $stage,
                null,
                ApprovalActionType::Expire,
                $reason,
                ['automatic' => true],
            );

            $this->skipOpenStages($request, $stage);
            $this->finaliseRequest($request, ApprovalStatus::Expired, $reason);
            $this->syncDocument($request->refresh());

            event(new ApprovalExpired($request->refresh()));

            return $request->refresh()->load('stages.assignees');
        });
    }

    public function pendingFor(User $user): Collection
    {
        $delegations = ApprovalDelegation::query()
            ->active()
            ->where('delegate_id', $user->id)
            ->get();

        $statuses = [ApprovalRequestStageStatus::InProgress->value];

        if ($user->can(Permissions::WORKFLOW_OVERRIDE)) {
            $statuses[] = ApprovalRequestStageStatus::Blocked->value;
        }

        return ApprovalRequest::query()
            ->pending()
            ->whereHas('stages', function ($query) use ($user, $delegations, $statuses): void {
                $query->whereColumn('approval_request_stages.sequence', 'approval_requests.current_stage_sequence')
                    ->whereIn('approval_request_stages.status', $statuses)
                    ->where(function ($query) use ($user, $delegations): void {
                        $query->whereHas('assignees', function ($query) use ($user, $delegations): void {
                            $query->where('status', ApprovalStageAssigneeStatus::Pending->value)
                                ->where(function ($query) use ($user, $delegations): void {
                                    $query->where('user_id', $user->id);

                                    foreach ($delegations as $delegation) {
                                        $query->orWhere(function ($query) use ($delegation): void {
                                            $query->where('user_id', $delegation->delegator_id);

                                            if ($delegation->document_type !== null) {
                                                $query->whereHas('stage.request', function ($query) use ($delegation): void {
                                                    $query->where('document_type', $delegation->document_type);
                                                });
                                            }
                                        });
                                    }
                                });
                        });

                        if ($user->can(Permissions::WORKFLOW_OVERRIDE)) {
                            $query->orWhere('approval_request_stages.status', ApprovalRequestStageStatus::Blocked->value);
                        }
                    });
            })
            ->with(['flow', 'requester', 'stages.assignees'])
            ->orderBy('requested_at')
            ->get();
    }

    public function historyFor(Model&Approvable $document): Collection
    {
        $requestIds = ApprovalRequest::query()
            ->forDocument($document)
            ->pluck('id');

        return ApprovalAction::query()
            ->whereIn('approval_request_id', $requestIds)
            ->with(['actor', 'stage'])
            ->orderBy('acted_at')
            ->orderBy('id')
            ->get();
    }

    private function autoApprove(ApprovalRequest $request, Model&Approvable $document): void
    {
        $this->recordAction(
            $request,
            null,
            null,
            ApprovalActionType::Approve,
            'No applicable approval stages; auto-approved.',
            ['system' => true, 'reason' => 'no_applicable_stages'],
        );

        $this->finaliseRequest($request, ApprovalStatus::Approved, 'No applicable approval stages.');
        $document->onApprovalStatusChanged($request->refresh());
        $this->notifier->decided($request->refresh(), ApprovalStatus::Approved);

        event(new DocumentApproved($request->refresh()));
    }

    private function activateStage(ApprovalRequest $request, ApprovalRequestStage $stage): void
    {
        $assigneeCount = $stage->assignees()->where('status', ApprovalStageAssigneeStatus::Pending->value)->count();

        $attributes = [
            'started_at' => now(),
            'sla_due_at' => $stage->stage?->sla_hours !== null
                ? now()->addHours($stage->stage->sla_hours)
                : null,
            'blocked_reason' => null,
        ];

        if ($assigneeCount === 0) {
            $attributes['status'] = ApprovalRequestStageStatus::Blocked;
            $attributes['blocked_reason'] = 'No eligible approvers resolved for this stage.';
        } else {
            $attributes['status'] = ApprovalRequestStageStatus::InProgress;
        }

        $stage->forceFill($attributes)->save();

        $request->forceFill(['current_stage_sequence' => $stage->sequence])->save();
    }

    private function completeStage(ApprovalRequest $request, ApprovalRequestStage $stage): void
    {
        $stage->forceFill([
            'status' => ApprovalRequestStageStatus::Approved,
            'completed_at' => now(),
            'blocked_reason' => null,
        ])->save();

        event(new ApprovalStageCompleted($request->refresh(), $stage->refresh()));

        if ($stage->is_final) {
            $this->finaliseRequest($request, ApprovalStatus::Approved);
            $this->notifier->decided($request->refresh(), ApprovalStatus::Approved);
            event(new DocumentApproved($request->refresh()));

            return;
        }

        $next = $request->stages()
            ->where('sequence', '>', $stage->sequence)
            ->where('status', ApprovalRequestStageStatus::Pending->value)
            ->orderBy('sequence')
            ->first();

        if ($next === null) {
            $this->finaliseRequest($request, ApprovalStatus::Approved);
            $this->notifier->decided($request->refresh(), ApprovalStatus::Approved);
            event(new DocumentApproved($request->refresh()));

            return;
        }

        $this->activateStage($request, $next);

        if ($next->status === ApprovalRequestStageStatus::InProgress) {
            $this->notifier->assigned($request->refresh(), $next->refresh());
        }
    }

    private function finaliseRequest(ApprovalRequest $request, ApprovalStatus $status, ?string $comment = null): void
    {
        $request->forceFill([
            'status' => $status,
            'current_stage_sequence' => null,
            'completed_at' => now(),
            'active_key' => null,
        ])->save();

        $request->stages()
            ->whereIn('status', [
                ApprovalRequestStageStatus::Pending->value,
                ApprovalRequestStageStatus::InProgress->value,
                ApprovalRequestStageStatus::Blocked->value,
            ])
            ->update([
                'status' => ApprovalRequestStageStatus::Skipped->value,
                'completed_at' => now(),
            ]);

        if ($status !== ApprovalStatus::Approved) {
            $this->notifier->decided($request->refresh(), $status, $comment);
        }
    }

    private function skipOpenStages(ApprovalRequest $request, ?ApprovalRequestStage $current): void
    {
        $stageIds = $request->stages()
            ->whereIn('status', [
                ApprovalRequestStageStatus::Pending->value,
                ApprovalRequestStageStatus::InProgress->value,
                ApprovalRequestStageStatus::Blocked->value,
            ])
            ->pluck('id');

        ApprovalStageAssignee::query()
            ->whereIn('approval_request_stage_id', $stageIds)
            ->where('status', ApprovalStageAssigneeStatus::Pending->value)
            ->update(['status' => ApprovalStageAssigneeStatus::Skipped->value, 'acted_at' => now()]);

        $request->stages()
            ->whereIn('id', $stageIds)
            ->update([
                'status' => ApprovalRequestStageStatus::Skipped->value,
                'completed_at' => now(),
            ]);
    }

    private function findEffectiveAssignee(ApprovalRequestStage $stage, User $actor, string $documentType): ?ApprovalStageAssignee
    {
        $direct = $stage->assignees()->where('user_id', $actor->id)->first();

        if ($direct !== null) {
            return $direct;
        }

        $delegation = ApprovalDelegation::query()
            ->active()
            ->forDocumentType($documentType)
            ->where('delegate_id', $actor->id)
            ->whereIn('delegator_id', $stage->assignees()->pluck('user_id'))
            ->first();

        if ($delegation === null) {
            return null;
        }

        return $stage->assignees()->where('user_id', $delegation->delegator_id)->first();
    }

    private function isSelfApproval(ApprovalRequest $request, User $actor): bool
    {
        $createdBy = $request->metadata['created_by'] ?? null;
        $updatedBy = $request->metadata['updated_by'] ?? null;

        return (int) $createdBy === $actor->id || (int) $updatedBy === $actor->id;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return list<int>
     */
    private function selfApprovalExclusions(array $attributes): array
    {
        return collect([$attributes['created_by'] ?? null, $attributes['updated_by'] ?? null])
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function recordAction(
        ApprovalRequest $request,
        ?ApprovalRequestStage $stage,
        ?User $actor,
        ApprovalActionType $action,
        ?string $comment,
        array $metadata = [],
    ): ApprovalAction {
        return ApprovalAction::create([
            'approval_request_id' => $request->id,
            'approval_request_stage_id' => $stage?->id,
            'actor_id' => $actor?->id,
            'action' => $action,
            'comment' => $comment,
            'ip_address' => $actor !== null ? request()->ip() : null,
            'acted_at' => now(),
            'metadata' => $metadata === [] ? null : $metadata,
            'created_at' => now(),
        ]);
    }

    private function syncDocument(ApprovalRequest $request): void
    {
        $document = $request->approvable();

        if ($document !== null) {
            $document->onApprovalStatusChanged($request->refresh());
        }
    }
}
