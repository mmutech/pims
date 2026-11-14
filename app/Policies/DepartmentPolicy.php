<?php

namespace App\Policies;

use App\Domain\Organization\Models\Department;
use App\Models\User;
use App\Support\Permissions;

class DepartmentPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isSuperAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::ORG_DEPARTMENTS_VIEW);
    }

    public function view(User $user, Department $department): bool
    {
        return $user->can(Permissions::ORG_DEPARTMENTS_VIEW);
    }

    public function create(User $user): bool
    {
        return $user->can(Permissions::ORG_DEPARTMENTS_CREATE);
    }

    public function update(User $user, Department $department): bool
    {
        return $user->can(Permissions::ORG_DEPARTMENTS_UPDATE);
    }

    public function delete(User $user, Department $department): bool
    {
        return $user->can(Permissions::ORG_DEPARTMENTS_DELETE);
    }
}
