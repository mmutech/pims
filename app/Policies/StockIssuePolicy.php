<?php

namespace App\Policies;

use App\Domain\Distribution\Models\StockIssue;
use App\Enums\IssueStatus;
use App\Enums\WarehouseAccessLevel;
use App\Models\User;
use App\Support\Permissions;

class StockIssuePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isSuperAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::REQUISITION_ISSUES_VIEW);
    }

    public function view(User $user, StockIssue $issue): bool
    {
        return $user->can(Permissions::REQUISITION_ISSUES_VIEW)
            && $this->canAccess($user, $issue);
    }

    public function create(User $user): bool
    {
        return $user->can(Permissions::REQUISITION_ISSUES_CREATE);
    }

    public function update(User $user, StockIssue $issue): bool
    {
        return $user->can(Permissions::REQUISITION_ISSUES_UPDATE)
            && $this->canOperate($user, $issue)
            && $issue->canBeEdited();
    }

    public function delete(User $user, StockIssue $issue): bool
    {
        return $user->can(Permissions::REQUISITION_ISSUES_DELETE)
            && $this->canOperate($user, $issue)
            && $issue->canBeEdited();
    }

    public function post(User $user, StockIssue $issue): bool
    {
        return $user->can(Permissions::REQUISITION_ISSUES_POST)
            && $this->canOperate($user, $issue)
            && $issue->canBePosted();
    }

    public function reverse(User $user, StockIssue $issue): bool
    {
        return $user->can(Permissions::REQUISITION_ISSUES_REVERSE)
            && $this->canOperate($user, $issue)
            && $issue->canBeReversed();
    }

    public function cancel(User $user, StockIssue $issue): bool
    {
        return $user->can(Permissions::REQUISITION_ISSUES_CANCEL)
            && $this->canOperate($user, $issue)
            && $issue->canBeCancelled();
    }

    public function acknowledge(User $user, StockIssue $issue): bool
    {
        if (! $user->can(Permissions::REQUISITION_ISSUES_ACKNOWLEDGE)) {
            return false;
        }

        if ($issue->status !== IssueStatus::Posted || $issue->isAcknowledged()) {
            return false;
        }

        if ($user->can(Permissions::WORKFLOW_OVERRIDE)) {
            return true;
        }

        return (int) $issue->issued_to === (int) $user->id
            && (int) $issue->issued_by !== (int) $user->id;
    }

    private function canAccess(User $user, StockIssue $issue): bool
    {
        if ($user->hasGlobalAccess()
            || (int) $issue->issued_to === (int) $user->id
            || (int) $issue->issued_by === (int) $user->id
            || (int) $issue->created_by === (int) $user->id) {
            return true;
        }

        $warehouse = $issue->warehouse;

        if ($warehouse !== null && $user->hasWarehouseAccess($warehouse, WarehouseAccessLevel::View)) {
            return true;
        }

        return false;
    }

    private function canOperate(User $user, StockIssue $issue): bool
    {
        if ($user->hasGlobalAccess()) {
            return true;
        }

        $warehouse = $issue->warehouse;

        return $warehouse !== null && $user->hasWarehouseAccess($warehouse, WarehouseAccessLevel::Operate);
    }
}
