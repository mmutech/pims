<?php

namespace App\Policies;

use App\Domain\Receiving\Models\GoodsReceivedNote;
use App\Enums\WarehouseAccessLevel;
use App\Models\User;
use App\Support\Permissions;

class GoodsReceivedNotePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isSuperAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::RECEIVING_GRN_VIEW);
    }

    public function view(User $user, GoodsReceivedNote $grn): bool
    {
        return $user->can(Permissions::RECEIVING_GRN_VIEW)
            && $this->canAccess($user, $grn);
    }

    public function create(User $user): bool
    {
        return $user->can(Permissions::RECEIVING_GRN_CREATE);
    }

    public function post(User $user, GoodsReceivedNote $grn): bool
    {
        return $user->can(Permissions::RECEIVING_GRN_POST)
            && $this->canOperate($user, $grn)
            && $grn->canBePosted();
    }

    public function cancel(User $user, GoodsReceivedNote $grn): bool
    {
        return $user->can(Permissions::RECEIVING_GRN_CANCEL)
            && $this->canOperate($user, $grn)
            && $grn->canBeCancelled();
    }

    public function reverse(User $user, GoodsReceivedNote $grn): bool
    {
        return $user->can(Permissions::RECEIVING_GRN_REVERSE)
            && $this->canOperate($user, $grn)
            && $grn->canBeReversed();
    }

    private function canAccess(User $user, GoodsReceivedNote $grn): bool
    {
        if ($user->hasGlobalAccess() || $user->id === $grn->created_by) {
            return true;
        }

        $warehouse = $grn->warehouse;

        return $warehouse !== null && $user->hasWarehouseAccess($warehouse, WarehouseAccessLevel::View);
    }

    private function canOperate(User $user, GoodsReceivedNote $grn): bool
    {
        if ($user->hasGlobalAccess()) {
            return true;
        }

        $warehouse = $grn->warehouse;

        return $warehouse !== null && $user->hasWarehouseAccess($warehouse, WarehouseAccessLevel::Operate);
    }
}
