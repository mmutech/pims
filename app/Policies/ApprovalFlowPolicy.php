<?php

namespace App\Policies;

use App\Domain\Workflow\Models\ApprovalFlow;
use App\Models\User;
use App\Support\Permissions;

class ApprovalFlowPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isSuperAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::WORKFLOW_FLOWS_VIEW);
    }

    public function view(User $user, ApprovalFlow $flow): bool
    {
        return $user->can(Permissions::WORKFLOW_FLOWS_VIEW);
    }

    public function create(User $user): bool
    {
        return $user->can(Permissions::WORKFLOW_FLOWS_CREATE);
    }

    public function update(User $user, ApprovalFlow $flow): bool
    {
        return $user->can(Permissions::WORKFLOW_FLOWS_UPDATE);
    }

    public function delete(User $user, ApprovalFlow $flow): bool
    {
        return $user->can(Permissions::WORKFLOW_FLOWS_DELETE);
    }
}
