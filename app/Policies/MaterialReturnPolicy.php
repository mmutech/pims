<?php

namespace App\Policies;

use App\Domain\Distribution\Models\MaterialReturn;
use App\Enums\WarehouseAccessLevel;
use App\Models\User;
use App\Support\Permissions;

class MaterialReturnPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isSuperAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::DISTRIBUTION_RETURNS_VIEW);
    }

    public function view(User $user, MaterialReturn $return): bool
    {
        return $user->can(Permissions::DISTRIBUTION_RETURNS_VIEW)
            && $this->canAccess($user, $return);
    }

    public function create(User $user): bool
    {
        return $user->can(Permissions::DISTRIBUTION_RETURNS_CREATE);
    }

    public function update(User $user, MaterialReturn $return): bool
    {
        return $user->can(Permissions::DISTRIBUTION_RETURNS_UPDATE)
            && $this->canOperate($user, $return)
            && $return->canBeEdited();
    }

    public function delete(User $user, MaterialReturn $return): bool
    {
        return $user->can(Permissions::DISTRIBUTION_RETURNS_DELETE)
            && $this->canOperate($user, $return)
            && $return->canBeEdited();
    }

    public function assess(User $user, MaterialReturn $return): bool
    {
        if (! $user->can(Permissions::DISTRIBUTION_RETURNS_ASSESS)
            || ! $this->canOperate($user, $return)
            || ! $return->canBeAssessed()) {
            return false;
        }

        if ($user->can(Permissions::WORKFLOW_OVERRIDE)) {
            return true;
        }

        return (int) $return->returned_by !== (int) $user->id
            && (int) $return->created_by !== (int) $user->id;
    }

    public function post(User $user, MaterialReturn $return): bool
    {
        return $user->can(Permissions::DISTRIBUTION_RETURNS_POST)
            && $this->canOperate($user, $return)
            && $return->canBePosted();
    }

    public function cancel(User $user, MaterialReturn $return): bool
    {
        return $user->can(Permissions::DISTRIBUTION_RETURNS_CANCEL)
            && $this->canOperate($user, $return)
            && $return->canBeCancelled();
    }

    private function canAccess(User $user, MaterialReturn $return): bool
    {
        if ($user->hasGlobalAccess()
            || (int) $return->returned_by === (int) $user->id
            || (int) $return->created_by === (int) $user->id) {
            return true;
        }

        $warehouse = $return->warehouse;

        return $warehouse !== null && $user->hasWarehouseAccess($warehouse, WarehouseAccessLevel::View);
    }

    private function canOperate(User $user, MaterialReturn $return): bool
    {
        if ($user->hasGlobalAccess()) {
            return true;
        }

        $warehouse = $return->warehouse;

        return $warehouse !== null && $user->hasWarehouseAccess($warehouse, WarehouseAccessLevel::Operate);
    }
}
