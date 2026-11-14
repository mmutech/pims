<?php

namespace App\Policies;

use App\Domain\Warehouse\Models\WarehouseLocation;
use App\Enums\WarehouseAccessLevel;
use App\Models\User;
use App\Support\Permissions;

class WarehouseLocationPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isSuperAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::WAREHOUSE_LOCATIONS_VIEW);
    }

    public function view(User $user, WarehouseLocation $location): bool
    {
        return $user->can(Permissions::WAREHOUSE_LOCATIONS_VIEW)
            && $user->hasWarehouseAccess($location->warehouse, WarehouseAccessLevel::View);
    }

    public function create(User $user): bool
    {
        return $user->can(Permissions::WAREHOUSE_LOCATIONS_CREATE);
    }

    public function update(User $user, WarehouseLocation $location): bool
    {
        return $user->can(Permissions::WAREHOUSE_LOCATIONS_UPDATE)
            && $user->hasWarehouseAccess($location->warehouse, WarehouseAccessLevel::Manage);
    }

    public function delete(User $user, WarehouseLocation $location): bool
    {
        return $user->can(Permissions::WAREHOUSE_LOCATIONS_DELETE)
            && $user->hasWarehouseAccess($location->warehouse, WarehouseAccessLevel::Manage);
    }
}
