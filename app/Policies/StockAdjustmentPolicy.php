<?php

namespace App\Policies;

use App\Domain\Stocktaking\Models\StockAdjustment;
use App\Enums\WarehouseAccessLevel;
use App\Models\User;
use App\Support\Permissions;

class StockAdjustmentPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isSuperAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::INVENTORY_ADJUSTMENTS_VIEW);
    }

    public function view(User $user, StockAdjustment $adjustment): bool
    {
        return $user->can(Permissions::INVENTORY_ADJUSTMENTS_VIEW)
            && $this->canAccess($user, $adjustment);
    }

    public function create(User $user): bool
    {
        return $user->can(Permissions::INVENTORY_ADJUSTMENTS_CREATE);
    }

    public function update(User $user, StockAdjustment $adjustment): bool
    {
        return $user->can(Permissions::INVENTORY_ADJUSTMENTS_UPDATE)
            && $this->canOperate($user, $adjustment)
            && $adjustment->canBeEdited();
    }

    public function delete(User $user, StockAdjustment $adjustment): bool
    {
        return $user->can(Permissions::INVENTORY_ADJUSTMENTS_DELETE)
            && $this->canOperate($user, $adjustment)
            && $adjustment->canBeEdited();
    }

    public function submit(User $user, StockAdjustment $adjustment): bool
    {
        return $user->can(Permissions::INVENTORY_ADJUSTMENTS_SUBMIT)
            && $this->canOperate($user, $adjustment)
            && $adjustment->canBeSubmitted();
    }

    public function post(User $user, StockAdjustment $adjustment): bool
    {
        if (! $user->can(Permissions::INVENTORY_ADJUSTMENTS_POST)
            || ! $this->canOperate($user, $adjustment)
            || ! $adjustment->canBePosted()) {
            return false;
        }

        if ($adjustment->requiresApproval() && $adjustment->approved_at === null) {
            return false;
        }

        if ($user->can(Permissions::WORKFLOW_OVERRIDE)) {
            return true;
        }

        return ! $adjustment->isCreatedBy($user);
    }

    public function reverse(User $user, StockAdjustment $adjustment): bool
    {
        if (! $user->can(Permissions::INVENTORY_ADJUSTMENTS_REVERSE)
            || ! $this->canOperate($user, $adjustment)
            || ! $adjustment->canBeReversed()) {
            return false;
        }

        if ($user->can(Permissions::WORKFLOW_OVERRIDE)) {
            return true;
        }

        return (int) $adjustment->posted_by !== (int) $user->id;
    }

    public function cancel(User $user, StockAdjustment $adjustment): bool
    {
        return $user->can(Permissions::INVENTORY_ADJUSTMENTS_CANCEL)
            && $this->canOperate($user, $adjustment)
            && $adjustment->canBeCancelled();
    }

    private function canAccess(User $user, StockAdjustment $adjustment): bool
    {
        if ($user->hasGlobalAccess() || $adjustment->isCreatedBy($user)) {
            return true;
        }

        $warehouse = $adjustment->warehouse;

        return $warehouse !== null && $user->hasWarehouseAccess($warehouse, WarehouseAccessLevel::View);
    }

    private function canOperate(User $user, StockAdjustment $adjustment): bool
    {
        if ($user->hasGlobalAccess()) {
            return true;
        }

        $warehouse = $adjustment->warehouse;

        return $warehouse !== null && $user->hasWarehouseAccess($warehouse, WarehouseAccessLevel::Operate);
    }
}
