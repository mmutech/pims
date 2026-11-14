<?php

namespace App\Policies;

use App\Domain\Workflow\Models\ApprovalDelegation;
use App\Models\User;
use App\Support\Permissions;

class ApprovalDelegationPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isSuperAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::WORKFLOW_REQUESTS_VIEW);
    }

    public function view(User $user, ApprovalDelegation $delegation): bool
    {
        return $delegation->delegator_id === $user->id
            || $delegation->delegate_id === $user->id
            || $user->can(Permissions::WORKFLOW_OVERRIDE);
    }

    public function create(User $user): bool
    {
        return $user->can(Permissions::WORKFLOW_DELEGATE);
    }

    public function delete(User $user, ApprovalDelegation $delegation): bool
    {
        return $delegation->delegator_id === $user->id
            || $user->can(Permissions::WORKFLOW_OVERRIDE);
    }
}
