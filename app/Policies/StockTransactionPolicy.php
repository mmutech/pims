<?php

namespace App\Policies;

use App\Domain\Inventory\Models\StockTransaction;
use App\Enums\WarehouseAccessLevel;
use App\Models\User;
use App\Support\Permissions;

class StockTransactionPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isSuperAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::INVENTORY_TRANSACTIONS_VIEW);
    }

    public function view(User $user, StockTransaction $transaction): bool
    {
        if (! $user->can(Permissions::INVENTORY_TRANSACTIONS_VIEW)) {
            return false;
        }

        if ($user->hasGlobalAccess()) {
            return true;
        }

        $warehouse = $transaction->warehouse;

        return $warehouse !== null && $user->hasWarehouseAccess($warehouse, WarehouseAccessLevel::View);
    }
}
