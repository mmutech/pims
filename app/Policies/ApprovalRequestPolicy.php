<?php

namespace App\Policies;

use App\Domain\Workflow\Models\ApprovalRequest;
use App\Models\User;
use App\Support\Permissions;

class ApprovalRequestPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isSuperAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::WORKFLOW_REQUESTS_VIEW);
    }

    public function view(User $user, ApprovalRequest $request): bool
    {
        return $user->can(Permissions::WORKFLOW_OVERRIDE)
            || $user->can(Permissions::AUDIT_VIEW)
            || $request->requested_by === $user->id
            || $request->isAssignedTo($user);
    }

    public function act(User $user, ApprovalRequest $request): bool
    {
        return $user->can(Permissions::WORKFLOW_ACT)
            && (
                $user->can(Permissions::WORKFLOW_OVERRIDE)
                || $request->isAssignedToCurrentStage($user)
                || $request->isDelegatedToCurrentStage($user)
            );
    }

    public function cancel(User $user, ApprovalRequest $request): bool
    {
        return $request->requested_by === $user->id || $user->can(Permissions::WORKFLOW_OVERRIDE);
    }

    public function delegate(User $user, ApprovalRequest $request): bool
    {
        return $user->can(Permissions::WORKFLOW_DELEGATE);
    }

    public function expire(User $user, ApprovalRequest $request): bool
    {
        return $user->can(Permissions::WORKFLOW_OVERRIDE);
    }
}
