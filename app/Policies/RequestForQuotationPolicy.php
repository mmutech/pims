<?php

namespace App\Policies;

use App\Domain\Procurement\Models\RequestForQuotation;
use App\Models\User;
use App\Support\Permissions;

class RequestForQuotationPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isSuperAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::PROCUREMENT_RFQS_VIEW);
    }

    public function view(User $user, RequestForQuotation $rfq): bool
    {
        return $user->can(Permissions::PROCUREMENT_RFQS_VIEW)
            && $this->canAccess($user, $rfq);
    }

    public function create(User $user): bool
    {
        return $user->can(Permissions::PROCUREMENT_RFQS_CREATE);
    }

    public function update(User $user, RequestForQuotation $rfq): bool
    {
        return $user->can(Permissions::PROCUREMENT_RFQS_UPDATE)
            && $this->canAccess($user, $rfq)
            && $rfq->canBeEdited();
    }

    public function delete(User $user, RequestForQuotation $rfq): bool
    {
        return $user->can(Permissions::PROCUREMENT_RFQS_DELETE)
            && $this->canAccess($user, $rfq)
            && $rfq->canBeEdited();
    }

    public function issue(User $user, RequestForQuotation $rfq): bool
    {
        return $user->can(Permissions::PROCUREMENT_RFQS_ISSUE)
            && $this->canAccess($user, $rfq)
            && $rfq->canBeIssued();
    }

    public function cancel(User $user, RequestForQuotation $rfq): bool
    {
        return $user->can(Permissions::PROCUREMENT_RFQS_CANCEL)
            && $this->canAccess($user, $rfq)
            && $rfq->canBeCancelled();
    }

    private function canAccess(User $user, RequestForQuotation $rfq): bool
    {
        return $user->hasGlobalAccess()
            || $user->id === $rfq->created_by;
    }
}
