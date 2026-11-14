<?php

namespace App\Policies;

use App\Domain\Distribution\Models\MaterialRequisition;
use App\Enums\RequisitionStatus;
use App\Enums\WarehouseAccessLevel;
use App\Models\User;
use App\Support\Permissions;

class MaterialRequisitionPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isSuperAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::REQUISITION_REQUESTS_VIEW);
    }

    public function view(User $user, MaterialRequisition $requisition): bool
    {
        return $user->can(Permissions::REQUISITION_REQUESTS_VIEW)
            && $this->canAccess($user, $requisition);
    }

    public function create(User $user): bool
    {
        return $user->can(Permissions::REQUISITION_REQUESTS_CREATE);
    }

    public function update(User $user, MaterialRequisition $requisition): bool
    {
        return $user->can(Permissions::REQUISITION_REQUESTS_UPDATE)
            && $this->canOperate($user, $requisition)
            && $requisition->canBeEdited();
    }

    public function delete(User $user, MaterialRequisition $requisition): bool
    {
        return $user->can(Permissions::REQUISITION_REQUESTS_DELETE)
            && $this->canOperate($user, $requisition)
            && $requisition->canBeEdited();
    }

    public function submit(User $user, MaterialRequisition $requisition): bool
    {
        return $user->can(Permissions::REQUISITION_REQUESTS_SUBMIT)
            && $this->canOperate($user, $requisition)
            && $requisition->canBeSubmitted();
    }

    public function cancel(User $user, MaterialRequisition $requisition): bool
    {
        return $user->can(Permissions::REQUISITION_REQUESTS_CANCEL)
            && $this->canOperate($user, $requisition)
            && $requisition->canBeCancelled();
    }

    public function reserve(User $user, MaterialRequisition $requisition): bool
    {
        return $user->can(Permissions::REQUISITION_REQUESTS_RESERVE)
            && $this->canOperate($user, $requisition)
            && $requisition->isApprovedForIssue();
    }

    public function adjustApprovedQuantities(User $user, MaterialRequisition $requisition): bool
    {
        return $user->can(Permissions::REQUISITION_REQUESTS_UPDATE)
            && $this->canAccess($user, $requisition)
            && in_array($requisition->status, [
                RequisitionStatus::Draft,
                RequisitionStatus::Submitted,
                RequisitionStatus::Returned,
            ], true);
    }

    private function canAccess(User $user, MaterialRequisition $requisition): bool
    {
        if ($user->hasGlobalAccess()
            || (int) $requisition->requested_by === (int) $user->id
            || (int) $requisition->created_by === (int) $user->id) {
            return true;
        }

        $warehouse = $requisition->destinationWarehouse;

        return $warehouse !== null && $user->hasWarehouseAccess($warehouse, WarehouseAccessLevel::View);
    }

    private function canOperate(User $user, MaterialRequisition $requisition): bool
    {
        if ($user->hasGlobalAccess()
            || (int) $requisition->requested_by === (int) $user->id) {
            return true;
        }

        $warehouse = $requisition->destinationWarehouse;

        return $warehouse !== null && $user->hasWarehouseAccess($warehouse, WarehouseAccessLevel::Operate);
    }
}
