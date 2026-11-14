<?php

namespace App\Policies;

use App\Domain\Warehouse\Models\Warehouse;
use App\Enums\WarehouseAccessLevel;
use App\Models\User;
use App\Support\Permissions;

class WarehousePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isSuperAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::WAREHOUSES_VIEW);
    }

    public function view(User $user, Warehouse $warehouse): bool
    {
        return $user->can(Permissions::WAREHOUSES_VIEW)
            && $user->hasWarehouseAccess($warehouse, WarehouseAccessLevel::View);
    }

    public function create(User $user): bool
    {
        return $user->can(Permissions::WAREHOUSES_CREATE);
    }

    public function update(User $user, Warehouse $warehouse): bool
    {
        return $user->can(Permissions::WAREHOUSES_UPDATE)
            && $user->hasWarehouseAccess($warehouse, WarehouseAccessLevel::Manage);
    }

    public function delete(User $user, Warehouse $warehouse): bool
    {
        return $user->can(Permissions::WAREHOUSES_DELETE)
            && $user->hasWarehouseAccess($warehouse, WarehouseAccessLevel::Manage);
    }

    public function manageAccess(User $user, Warehouse $warehouse): bool
    {
        return $user->can(Permissions::WAREHOUSE_ACCESS_MANAGE)
            && $user->hasWarehouseAccess($warehouse, WarehouseAccessLevel::Manage);
    }
}
