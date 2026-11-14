<?php

namespace App\Policies;

use App\Domain\Stocktaking\Models\StockReconciliation;
use App\Enums\WarehouseAccessLevel;
use App\Models\User;
use App\Support\Permissions;

class StockReconciliationPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isSuperAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::STOCKTAKING_RECONCILIATIONS_VIEW);
    }

    public function view(User $user, StockReconciliation $reconciliation): bool
    {
        return $user->can(Permissions::STOCKTAKING_RECONCILIATIONS_VIEW)
            && $this->canAccess($user, $reconciliation);
    }

    public function prepare(User $user, StockReconciliation $reconciliation): bool
    {
        return ($user->can(Permissions::STOCKTAKING_RECONCILE)
                || $user->can(Permissions::STOCKTAKING_RECONCILIATIONS_PREPARE))
            && $this->canOperate($user, $reconciliation)
            && $reconciliation->canBeApproved();
    }

    public function approve(User $user, StockReconciliation $reconciliation): bool
    {
        if (! $user->can(Permissions::STOCKTAKING_RECONCILIATIONS_APPROVE)
            || ! $this->canOperate($user, $reconciliation)
            || ! $reconciliation->canBeApproved()) {
            return false;
        }

        if ($user->can(Permissions::WORKFLOW_OVERRIDE)) {
            return true;
        }

        return (int) $reconciliation->prepared_by !== (int) $user->id;
    }

    public function post(User $user, StockReconciliation $reconciliation): bool
    {
        return $user->can(Permissions::STOCKTAKING_RECONCILIATIONS_POST)
            && $this->canOperate($user, $reconciliation)
            && $reconciliation->canBePosted();
    }

    private function canAccess(User $user, StockReconciliation $reconciliation): bool
    {
        if ($user->hasGlobalAccess() || (int) $reconciliation->prepared_by === (int) $user->id) {
            return true;
        }

        $warehouse = $reconciliation->warehouse;

        return $warehouse !== null && $user->hasWarehouseAccess($warehouse, WarehouseAccessLevel::View);
    }

    private function canOperate(User $user, StockReconciliation $reconciliation): bool
    {
        if ($user->hasGlobalAccess()) {
            return true;
        }

        $warehouse = $reconciliation->warehouse;

        return $warehouse !== null && $user->hasWarehouseAccess($warehouse, WarehouseAccessLevel::Operate);
    }
}
