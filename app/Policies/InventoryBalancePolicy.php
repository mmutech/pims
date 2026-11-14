<?php

namespace App\Policies;

use App\Domain\Inventory\Models\InventoryBalance;
use App\Enums\WarehouseAccessLevel;
use App\Models\User;
use App\Support\Permissions;

class InventoryBalancePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isSuperAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::INVENTORY_BALANCES_VIEW);
    }

    public function view(User $user, InventoryBalance $balance): bool
    {
        if (! $user->can(Permissions::INVENTORY_BALANCES_VIEW)) {
            return false;
        }

        if ($user->hasGlobalAccess()) {
            return true;
        }

        $warehouse = $balance->warehouse;

        return $warehouse !== null && $user->hasWarehouseAccess($warehouse, WarehouseAccessLevel::View);
    }
}
