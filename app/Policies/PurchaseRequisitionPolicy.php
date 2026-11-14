<?php

namespace App\Policies;

use App\Domain\Procurement\Models\PurchaseRequisition;
use App\Models\User;
use App\Support\Permissions;

class PurchaseRequisitionPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isSuperAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::PROCUREMENT_REQUISITIONS_VIEW);
    }

    public function view(User $user, PurchaseRequisition $requisition): bool
    {
        return $user->can(Permissions::PROCUREMENT_REQUISITIONS_VIEW)
            && $this->canAccess($user, $requisition);
    }

    public function create(User $user): bool
    {
        return $user->can(Permissions::PROCUREMENT_REQUISITIONS_CREATE);
    }

    public function update(User $user, PurchaseRequisition $requisition): bool
    {
        return $user->can(Permissions::PROCUREMENT_REQUISITIONS_UPDATE)
            && $this->canAccess($user, $requisition)
            && $requisition->canBeEdited();
    }

    public function delete(User $user, PurchaseRequisition $requisition): bool
    {
        return $user->can(Permissions::PROCUREMENT_REQUISITIONS_DELETE)
            && $this->canAccess($user, $requisition)
            && $requisition->canBeEdited();
    }

    public function submit(User $user, PurchaseRequisition $requisition): bool
    {
        return $user->can(Permissions::PROCUREMENT_REQUISITIONS_SUBMIT)
            && $this->canAccess($user, $requisition)
            && $requisition->canBeSubmitted();
    }

    public function cancel(User $user, PurchaseRequisition $requisition): bool
    {
        return $user->can(Permissions::PROCUREMENT_REQUISITIONS_CANCEL)
            && $this->canAccess($user, $requisition)
            && $requisition->canBeCancelled();
    }

    public function adjustApprovedQuantities(User $user, PurchaseRequisition $requisition): bool
    {
        return $user->can(Permissions::PROCUREMENT_REQUISITIONS_UPDATE)
            && $this->canAccess($user, $requisition);
    }

    private function canAccess(User $user, PurchaseRequisition $requisition): bool
    {
        return $user->hasGlobalAccess()
            || $user->id === $requisition->requested_by;
    }
}
