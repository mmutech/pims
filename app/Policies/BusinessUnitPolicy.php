<?php

namespace App\Policies;

use App\Domain\Organization\Models\BusinessUnit;
use App\Models\User;
use App\Support\Permissions;

class BusinessUnitPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isSuperAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::ORG_BUSINESS_UNITS_VIEW);
    }

    public function view(User $user, BusinessUnit $businessUnit): bool
    {
        return $user->can(Permissions::ORG_BUSINESS_UNITS_VIEW);
    }

    public function create(User $user): bool
    {
        return $user->can(Permissions::ORG_BUSINESS_UNITS_CREATE);
    }

    public function update(User $user, BusinessUnit $businessUnit): bool
    {
        return $user->can(Permissions::ORG_BUSINESS_UNITS_UPDATE);
    }

    public function delete(User $user, BusinessUnit $businessUnit): bool
    {
        return $user->can(Permissions::ORG_BUSINESS_UNITS_DELETE);
    }
}
