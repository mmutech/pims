<?php

namespace App\Policies;

use App\Models\User;
use App\Support\Permissions;
use Spatie\Activitylog\Models\Activity;

class ActivityPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isSuperAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::AUDIT_VIEW);
    }

    public function view(User $user, Activity $activity): bool
    {
        return $user->can(Permissions::AUDIT_VIEW);
    }
}
