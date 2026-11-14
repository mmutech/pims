<?php

namespace App\Policies;

use App\Domain\Receiving\Models\StoreReceiptAdvice;
use App\Enums\WarehouseAccessLevel;
use App\Models\User;
use App\Support\Permissions;

class StoreReceiptAdvicePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isSuperAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::RECEIVING_SRA_VIEW);
    }

    public function view(User $user, StoreReceiptAdvice $sra): bool
    {
        return $user->can(Permissions::RECEIVING_SRA_VIEW)
            && $this->canAccess($user, $sra);
    }

    public function create(User $user): bool
    {
        return $user->can(Permissions::RECEIVING_SRA_CREATE);
    }

    public function update(User $user, StoreReceiptAdvice $sra): bool
    {
        return $user->can(Permissions::RECEIVING_SRA_UPDATE)
            && $this->canOperate($user, $sra)
            && $sra->canBeEdited();
    }

    public function delete(User $user, StoreReceiptAdvice $sra): bool
    {
        return $user->can(Permissions::RECEIVING_SRA_DELETE)
            && $this->canOperate($user, $sra)
            && $sra->canBeEdited();
    }

    public function submit(User $user, StoreReceiptAdvice $sra): bool
    {
        return $user->can(Permissions::RECEIVING_SRA_SUBMIT)
            && $this->canOperate($user, $sra)
            && $sra->canBeSubmitted();
    }

    public function cancel(User $user, StoreReceiptAdvice $sra): bool
    {
        return $user->can(Permissions::RECEIVING_SRA_CANCEL)
            && $this->canOperate($user, $sra)
            && $sra->canBeCancelled();
    }

    public function verify(User $user, StoreReceiptAdvice $sra): bool
    {
        return $user->can(Permissions::RECEIVING_SRA_VERIFY)
            && $this->canOperate($user, $sra)
            && $sra->canBeVerified();
    }

    public function startInspection(User $user, StoreReceiptAdvice $sra): bool
    {
        return $user->can(Permissions::RECEIVING_INSPECTIONS_START)
            && $this->canOperate($user, $sra)
            && $sra->canBeInspected();
    }

    public function createGoodsReceivedNote(User $user, StoreReceiptAdvice $sra): bool
    {
        return $user->can(Permissions::RECEIVING_GRN_CREATE)
            && $this->canOperate($user, $sra)
            && $sra->canBeReceived();
    }

    private function canAccess(User $user, StoreReceiptAdvice $sra): bool
    {
        if ($user->hasGlobalAccess() || $user->id === $sra->received_by) {
            return true;
        }

        $warehouse = $sra->warehouse;

        return $warehouse !== null && $user->hasWarehouseAccess($warehouse, WarehouseAccessLevel::View);
    }

    private function canOperate(User $user, StoreReceiptAdvice $sra): bool
    {
        if ($user->hasGlobalAccess()) {
            return true;
        }

        $warehouse = $sra->warehouse;

        return $warehouse !== null && $user->hasWarehouseAccess($warehouse, WarehouseAccessLevel::Operate);
    }
}
