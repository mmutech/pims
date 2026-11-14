<?php

namespace App\Domain\Workflow\Services;

use App\Domain\Workflow\Models\ApprovalEscalation;
use App\Domain\Workflow\Models\ApprovalNotification;
use App\Domain\Workflow\Models\ApprovalRequest;
use App\Domain\Workflow\Models\ApprovalRequestStage;
use App\Domain\Workflow\Notifications\ApprovalAssignedNotification;
use App\Domain\Workflow\Notifications\ApprovalDecisionNotification;
use App\Domain\Workflow\Notifications\ApprovalEscalatedNotification;
use App\Enums\ApprovalStatus;
use App\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

final class ApprovalNotifier
{
    public function assigned(ApprovalRequest $request, ApprovalRequestStage $stage): void
    {
        $notification = new ApprovalAssignedNotification($request, $stage);

        foreach ($stage->pendingAssignees()->with('user')->get() as $assignee) {
            if ($assignee->user === null) {
                continue;
            }

            $this->send($assignee->user, $request, $stage, 'assigned', $notification);
        }
    }

    public function decided(ApprovalRequest $request, ApprovalStatus $status, ?string $comment = null): void
    {
        $requester = $request->requester;

        if ($requester === null) {
            return;
        }

        $this->send(
            $requester,
            $request,
            null,
            'decision_'.$status->value,
            new ApprovalDecisionNotification($request, $status, $comment),
        );
    }

    public function escalated(ApprovalRequest $request, ApprovalRequestStage $stage, ApprovalEscalation $escalation): void
    {
        $targets = new Collection;

        if ($escalation->escalatedTo !== null) {
            $targets->push($escalation->escalatedTo);
        }

        if ($request->requester !== null) {
            $targets->push($request->requester);
        }

        $notification = new ApprovalEscalatedNotification($request, $stage, $escalation);

        foreach ($targets->unique('id') as $user) {
            $this->send($user, $request, $stage, 'escalated', $notification);
        }
    }

    private function send(
        User $user,
        ApprovalRequest $request,
        ?ApprovalRequestStage $stage,
        string $type,
        Notification $notification,
    ): void {
        $dedupeKey = implode(':', [
            $request->id,
            $stage?->id ?? '0',
            $user->id,
            $type,
        ]);

        $record = ApprovalNotification::query()->firstOrCreate(
            ['dedupe_key' => $dedupeKey],
            [
                'approval_request_id' => $request->id,
                'approval_request_stage_id' => $stage?->id,
                'user_id' => $user->id,
                'type' => $type,
                'sent_at' => null,
            ],
        );

        if (! $record->wasRecentlyCreated) {
            return;
        }

        $user->notify($notification);

        $record->forceFill(['sent_at' => now()])->save();
    }
}
