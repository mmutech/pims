<?php

namespace App\Policies;

use App\Domain\Procurement\Models\QuotationEvaluation;
use App\Models\User;
use App\Support\Permissions;

class QuotationEvaluationPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isSuperAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::PROCUREMENT_QUOTATIONS_VIEW);
    }

    public function view(User $user, QuotationEvaluation $evaluation): bool
    {
        return $user->can(Permissions::PROCUREMENT_QUOTATIONS_VIEW)
            && $this->canAccess($user, $evaluation);
    }

    public function create(User $user): bool
    {
        return $user->can(Permissions::PROCUREMENT_QUOTATIONS_EVALUATE);
    }

    public function update(User $user, QuotationEvaluation $evaluation): bool
    {
        return $user->can(Permissions::PROCUREMENT_QUOTATIONS_EVALUATE)
            && $this->canAccess($user, $evaluation)
            && $evaluation->canBeEdited();
    }

    public function submit(User $user, QuotationEvaluation $evaluation): bool
    {
        return $user->can(Permissions::PROCUREMENT_QUOTATIONS_EVALUATE)
            && $this->canAccess($user, $evaluation)
            && $evaluation->canBeSubmitted();
    }

    public function cancel(User $user, QuotationEvaluation $evaluation): bool
    {
        return $user->can(Permissions::PROCUREMENT_QUOTATIONS_EVALUATE)
            && $this->canAccess($user, $evaluation)
            && $evaluation->status->value !== 'approved';
    }

    private function canAccess(User $user, QuotationEvaluation $evaluation): bool
    {
        return $user->hasGlobalAccess()
            || $user->id === $evaluation->created_by;
    }
}
