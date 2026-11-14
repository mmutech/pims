<?php

namespace App\Policies;

use App\Domain\Stocktaking\Models\StockCount;
use App\Enums\WarehouseAccessLevel;
use App\Models\User;
use App\Support\Permissions;

class StockCountPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isSuperAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::STOCKTAKING_COUNTS_VIEW);
    }

    public function view(User $user, StockCount $count): bool
    {
        return $user->can(Permissions::STOCKTAKING_COUNTS_VIEW)
            && $this->canAccess($user, $count);
    }

    public function create(User $user): bool
    {
        return $user->can(Permissions::STOCKTAKING_COUNTS_CREATE);
    }

    public function update(User $user, StockCount $count): bool
    {
        return $user->can(Permissions::STOCKTAKING_COUNTS_UPDATE)
            && $this->canOperate($user, $count)
            && $count->canBeStarted();
    }

    public function delete(User $user, StockCount $count): bool
    {
        return $user->can(Permissions::STOCKTAKING_COUNTS_DELETE)
            && $this->canOperate($user, $count)
            && $count->canBeStarted();
    }

    public function start(User $user, StockCount $count): bool
    {
        return $user->can(Permissions::STOCKTAKING_COUNTS_START)
            && $this->canOperate($user, $count)
            && $count->canBeStarted();
    }

    public function count(User $user, StockCount $count): bool
    {
        return $user->can(Permissions::STOCKTAKING_COUNTS_COUNT)
            && $this->canOperate($user, $count)
            && $count->canBeCounted();
    }

    public function recount(User $user, StockCount $count): bool
    {
        return $user->can(Permissions::STOCKTAKING_COUNTS_RECOUNT)
            && $this->canOperate($user, $count)
            && $count->canBeCounted();
    }

    public function analyse(User $user, StockCount $count): bool
    {
        return $user->can(Permissions::STOCKTAKING_COUNTS_ANALYSE)
            && $this->canOperate($user, $count)
            && $count->canBeAnalysed();
    }

    public function adjust(User $user, StockCount $count): bool
    {
        if (! $user->can(Permissions::STOCKTAKING_COUNTS_ADJUST)
            || ! $this->canOperate($user, $count)
            || ! $count->canBeAnalysed()) {
            return false;
        }

        if ($user->can(Permissions::WORKFLOW_OVERRIDE)) {
            return true;
        }

        if ((int) $count->created_by === (int) $user->id) {
            return false;
        }

        return ! $count->items()->where('counted_by', $user->id)->exists();
    }

    public function complete(User $user, StockCount $count): bool
    {
        return $user->can(Permissions::STOCKTAKING_COUNTS_COMPLETE)
            && $this->canOperate($user, $count)
            && $count->canBeCompleted();
    }

    public function cancel(User $user, StockCount $count): bool
    {
        return $user->can(Permissions::STOCKTAKING_COUNTS_CANCEL)
            && $this->canOperate($user, $count)
            && $count->canBeCancelled();
    }

    public function reconcile(User $user, StockCount $count): bool
    {
        return ($user->can(Permissions::STOCKTAKING_RECONCILE)
                || $user->can(Permissions::STOCKTAKING_RECONCILIATIONS_PREPARE))
            && $this->canOperate($user, $count)
            && $count->snapshot_at !== null;
    }

    private function canAccess(User $user, StockCount $count): bool
    {
        if ($user->hasGlobalAccess() || (int) $count->created_by === (int) $user->id) {
            return true;
        }

        $warehouse = $count->warehouse;

        return $warehouse !== null && $user->hasWarehouseAccess($warehouse, WarehouseAccessLevel::View);
    }

    private function canOperate(User $user, StockCount $count): bool
    {
        if ($user->hasGlobalAccess()) {
            return true;
        }

        $warehouse = $count->warehouse;

        return $warehouse !== null && $user->hasWarehouseAccess($warehouse, WarehouseAccessLevel::Operate);
    }
}
