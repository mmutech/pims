<?php

namespace App\Policies;

use App\Domain\Catalog\Models\UnitOfMeasure;
use App\Models\User;
use App\Support\Permissions;

class UnitOfMeasurePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isSuperAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::UNITS_VIEW);
    }

    public function view(User $user, UnitOfMeasure $unit): bool
    {
        return $user->can(Permissions::UNITS_VIEW);
    }

    public function create(User $user): bool
    {
        return $user->can(Permissions::UNITS_CREATE);
    }

    public function update(User $user, UnitOfMeasure $unit): bool
    {
        return $user->can(Permissions::UNITS_UPDATE);
    }

    public function delete(User $user, UnitOfMeasure $unit): bool
    {
        return $user->can(Permissions::UNITS_DELETE);
    }
}
