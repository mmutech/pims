<?php

namespace App\Policies;

use App\Domain\Organization\Models\CostCenter;
use App\Models\User;
use App\Support\Permissions;

class CostCenterPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isSuperAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::ORG_COST_CENTERS_VIEW);
    }

    public function view(User $user, CostCenter $costCenter): bool
    {
        return $user->can(Permissions::ORG_COST_CENTERS_VIEW);
    }

    public function create(User $user): bool
    {
        return $user->can(Permissions::ORG_COST_CENTERS_CREATE);
    }

    public function update(User $user, CostCenter $costCenter): bool
    {
        return $user->can(Permissions::ORG_COST_CENTERS_UPDATE);
    }

    public function delete(User $user, CostCenter $costCenter): bool
    {
        return $user->can(Permissions::ORG_COST_CENTERS_DELETE);
    }
}
