<?php

namespace App\Policies;

use App\Domain\Warehouse\Models\WarehouseBin;
use App\Enums\WarehouseAccessLevel;
use App\Models\User;
use App\Support\Permissions;

class WarehouseBinPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isSuperAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::WAREHOUSE_BINS_VIEW);
    }

    public function view(User $user, WarehouseBin $bin): bool
    {
        return $user->can(Permissions::WAREHOUSE_BINS_VIEW)
            && $user->hasWarehouseAccess($bin->location->warehouse, WarehouseAccessLevel::View);
    }

    public function create(User $user): bool
    {
        return $user->can(Permissions::WAREHOUSE_BINS_CREATE);
    }

    public function update(User $user, WarehouseBin $bin): bool
    {
        return $user->can(Permissions::WAREHOUSE_BINS_UPDATE)
            && $user->hasWarehouseAccess($bin->location->warehouse, WarehouseAccessLevel::Manage);
    }

    public function delete(User $user, WarehouseBin $bin): bool
    {
        return $user->can(Permissions::WAREHOUSE_BINS_DELETE)
            && $user->hasWarehouseAccess($bin->location->warehouse, WarehouseAccessLevel::Manage);
    }
}
