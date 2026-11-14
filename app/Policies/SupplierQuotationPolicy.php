<?php

namespace App\Policies;

use App\Domain\Procurement\Models\SupplierQuotation;
use App\Models\User;
use App\Support\Permissions;

class SupplierQuotationPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isSuperAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::PROCUREMENT_QUOTATIONS_VIEW);
    }

    public function view(User $user, SupplierQuotation $quotation): bool
    {
        return $user->can(Permissions::PROCUREMENT_QUOTATIONS_VIEW)
            && $this->canAccess($user, $quotation);
    }

    public function create(User $user): bool
    {
        return $user->can(Permissions::PROCUREMENT_QUOTATIONS_CREATE);
    }

    public function update(User $user, SupplierQuotation $quotation): bool
    {
        return $user->can(Permissions::PROCUREMENT_QUOTATIONS_UPDATE)
            && $this->canAccess($user, $quotation)
            && $quotation->canBeEdited();
    }

    public function delete(User $user, SupplierQuotation $quotation): bool
    {
        return $user->can(Permissions::PROCUREMENT_QUOTATIONS_DELETE)
            && $this->canAccess($user, $quotation)
            && $quotation->canBeEdited();
    }

    public function submit(User $user, SupplierQuotation $quotation): bool
    {
        return $user->can(Permissions::PROCUREMENT_QUOTATIONS_SUBMIT)
            && $this->canAccess($user, $quotation)
            && $quotation->canBeSubmitted();
    }

    public function withdraw(User $user, SupplierQuotation $quotation): bool
    {
        return $user->can(Permissions::PROCUREMENT_QUOTATIONS_UPDATE)
            && $this->canAccess($user, $quotation)
            && $quotation->canBeWithdrawn();
    }

    private function canAccess(User $user, SupplierQuotation $quotation): bool
    {
        return $user->hasGlobalAccess()
            || $user->id === $quotation->created_by;
    }
}
