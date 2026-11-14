<?php

namespace App\Policies;

use App\Domain\Receiving\Models\QualityInspection;
use App\Enums\WarehouseAccessLevel;
use App\Models\User;
use App\Support\Permissions;

class QualityInspectionPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isSuperAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::RECEIVING_INSPECTIONS_VIEW);
    }

    public function view(User $user, QualityInspection $inspection): bool
    {
        return $user->can(Permissions::RECEIVING_INSPECTIONS_VIEW)
            && $this->canAccess($user, $inspection);
    }

    public function start(User $user, QualityInspection $inspection): bool
    {
        return $user->can(Permissions::RECEIVING_INSPECTIONS_START)
            && $this->canOperate($user, $inspection)
            && ! $inspection->isComplete();
    }

    public function record(User $user, QualityInspection $inspection): bool
    {
        return $user->can(Permissions::RECEIVING_INSPECTIONS_RECORD)
            && $this->canOperate($user, $inspection)
            && ! $inspection->isComplete();
    }

    private function canAccess(User $user, QualityInspection $inspection): bool
    {
        if ($user->hasGlobalAccess() || $user->id === $inspection->inspector_id) {
            return true;
        }

        $warehouse = $inspection->warehouse;

        return $warehouse !== null && $user->hasWarehouseAccess($warehouse, WarehouseAccessLevel::View);
    }

    private function canOperate(User $user, QualityInspection $inspection): bool
    {
        if ($user->hasGlobalAccess()) {
            return true;
        }

        $warehouse = $inspection->warehouse;

        return $warehouse !== null && $user->hasWarehouseAccess($warehouse, WarehouseAccessLevel::Operate);
    }
}
