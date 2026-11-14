<?php

namespace App\Policies;

use App\Domain\Receiving\Models\SupplierReturn;
use App\Enums\WarehouseAccessLevel;
use App\Models\User;
use App\Support\Permissions;

class SupplierReturnPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isSuperAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::RECEIVING_RETURNS_VIEW);
    }

    public function view(User $user, SupplierReturn $return): bool
    {
        return $user->can(Permissions::RECEIVING_RETURNS_VIEW)
            && $this->canAccess($user, $return);
    }

    public function create(User $user): bool
    {
        return $user->can(Permissions::RECEIVING_RETURNS_CREATE);
    }

    public function update(User $user, SupplierReturn $return): bool
    {
        return $user->can(Permissions::RECEIVING_RETURNS_UPDATE)
            && $this->canOperate($user, $return)
            && $return->canBeEdited();
    }

    public function delete(User $user, SupplierReturn $return): bool
    {
        return $user->can(Permissions::RECEIVING_RETURNS_DELETE)
            && $this->canOperate($user, $return)
            && $return->canBeEdited();
    }

    public function post(User $user, SupplierReturn $return): bool
    {
        return $user->can(Permissions::RECEIVING_RETURNS_POST)
            && $this->canOperate($user, $return)
            && $return->canBePosted();
    }

    public function cancel(User $user, SupplierReturn $return): bool
    {
        return $user->can(Permissions::RECEIVING_RETURNS_CANCEL)
            && $this->canOperate($user, $return)
            && $return->canBeCancelled();
    }

    private function canAccess(User $user, SupplierReturn $return): bool
    {
        if ($user->hasGlobalAccess() || $user->id === $return->created_by) {
            return true;
        }

        $warehouse = $return->warehouse;

        return $warehouse !== null && $user->hasWarehouseAccess($warehouse, WarehouseAccessLevel::View);
    }

    private function canOperate(User $user, SupplierReturn $return): bool
    {
        if ($user->hasGlobalAccess()) {
            return true;
        }

        $warehouse = $return->warehouse;

        return $warehouse !== null && $user->hasWarehouseAccess($warehouse, WarehouseAccessLevel::Operate);
    }
}
