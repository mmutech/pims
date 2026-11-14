<?php

namespace App\Domain\Workflow\Services;

use App\Domain\Workflow\Models\ApprovalAction;
use App\Domain\Workflow\Models\ApprovalEscalation;
use App\Domain\Workflow\Models\ApprovalRequestStage;
use App\Enums\ApprovalActionType;
use App\Enums\ApprovalRequestStageStatus;
use App\Enums\ApprovalStageAssigneeStatus;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class ApprovalEscalationService
{
    public function __construct(private readonly ApprovalNotifier $notifier) {}

    public function escalateOverdueStages(): int
    {
        $count = 0;

        $stages = ApprovalRequestStage::query()
            ->overdue()
            ->with(['request.flow', 'stage.escalationUser', 'stage.escalationRole', 'assignees'])
            ->get();

        foreach ($stages as $stage) {
            $exists = ApprovalEscalation::query()
                ->where('approval_request_stage_id', $stage->id)
                ->whereNull('resolved_at')
                ->exists();

            if ($exists) {
                continue;
            }

            $this->escalate($stage);
            $count++;
        }

        return $count;
    }

    public function escalate(ApprovalRequestStage $stage, string $reason = 'Approval SLA breached'): ApprovalEscalation
    {
        return DB::transaction(function () use ($stage, $reason): ApprovalEscalation {
            $stage = ApprovalRequestStage::query()
                ->whereKey($stage->id)
                ->lockForUpdate()
                ->firstOrFail();

            $target = $this->resolveTarget($stage);

            $escalation = ApprovalEscalation::create([
                'approval_request_stage_id' => $stage->id,
                'escalated_to' => $target?->id,
                'reason' => $reason,
                'escalated_at' => now(),
                'metadata' => ['automatic' => true],
            ]);

            ApprovalAction::create([
                'approval_request_id' => $stage->approval_request_id,
                'approval_request_stage_id' => $stage->id,
                'actor_id' => null,
                'action' => ApprovalActionType::Escalate,
                'comment' => $reason,
                'ip_address' => null,
                'acted_at' => now(),
                'metadata' => [
                    'automatic' => true,
                    'escalated_to' => $target?->id,
                ],
                'created_at' => now(),
            ]);

            if ($target !== null && ! $stage->assignees()->where('user_id', $target->id)->exists()) {
                $stage->assignees()->create([
                    'user_id' => $target->id,
                    'status' => ApprovalStageAssigneeStatus::Pending,
                    'is_delegated' => false,
                    'delegated_from_id' => null,
                ]);
            }

            if ($stage->status === ApprovalRequestStageStatus::Blocked && $target !== null) {
                $stage->forceFill([
                    'status' => ApprovalRequestStageStatus::InProgress,
                    'blocked_reason' => null,
                    'started_at' => now(),
                ])->save();
            }

            $this->notifier->escalated($stage->refresh()->request, $stage->refresh(), $escalation);

            return $escalation->refresh();
        });
    }

    private function resolveTarget(ApprovalRequestStage $stage): ?User
    {
        $configuredUser = $stage->stage?->escalationUser;

        if ($configuredUser !== null && $configuredUser->isActive()) {
            return $configuredUser;
        }

        $role = $stage->stage?->escalationRole;

        if ($role !== null) {
            return User::query()
                ->where('status', UserStatus::Active->value)
                ->role($role->name)
                ->first();
        }

        return null;
    }
}
