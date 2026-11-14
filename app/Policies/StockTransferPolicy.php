<?php

namespace App\Policies;

use App\Domain\Distribution\Models\StockTransfer;
use App\Domain\Warehouse\Models\Warehouse;
use App\Enums\TransferStatus;
use App\Enums\WarehouseAccessLevel;
use App\Models\User;
use App\Support\Permissions;

class StockTransferPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isSuperAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::DISTRIBUTION_TRANSFERS_VIEW);
    }

    public function view(User $user, StockTransfer $transfer): bool
    {
        return $user->can(Permissions::DISTRIBUTION_TRANSFERS_VIEW)
            && $this->canAccess($user, $transfer);
    }

    public function create(User $user): bool
    {
        return $user->can(Permissions::DISTRIBUTION_TRANSFERS_CREATE);
    }

    public function update(User $user, StockTransfer $transfer): bool
    {
        return $user->can(Permissions::DISTRIBUTION_TRANSFERS_UPDATE)
            && $this->canOperateSource($user, $transfer)
            && $transfer->canBeEdited();
    }

    public function delete(User $user, StockTransfer $transfer): bool
    {
        return $user->can(Permissions::DISTRIBUTION_TRANSFERS_DELETE)
            && $this->canOperateSource($user, $transfer)
            && $transfer->status === TransferStatus::Draft;
    }

    public function submit(User $user, StockTransfer $transfer): bool
    {
        return $user->can(Permissions::DISTRIBUTION_TRANSFERS_SUBMIT)
            && $this->canOperateSource($user, $transfer)
            && $transfer->canBeSubmitted();
    }

    public function cancel(User $user, StockTransfer $transfer): bool
    {
        return $user->can(Permissions::DISTRIBUTION_TRANSFERS_CANCEL)
            && $this->canOperateSource($user, $transfer)
            && $transfer->canBeCancelled();
    }

    public function dispatch(User $user, StockTransfer $transfer): bool
    {
        return $user->can(Permissions::DISTRIBUTION_TRANSFERS_DISPATCH)
            && $this->canOperateSource($user, $transfer)
            && $transfer->canBeDispatched();
    }

    public function receive(User $user, StockTransfer $transfer): bool
    {
        return $user->can(Permissions::DISTRIBUTION_TRANSFERS_RECEIVE)
            && $this->canOperateDestination($user, $transfer)
            && in_array($transfer->status, [
                TransferStatus::InTransit,
                TransferStatus::PartiallyDispatched,
                TransferStatus::PartiallyReceived,
            ], true);
    }

    public function resolveDiscrepancy(User $user, StockTransfer $transfer): bool
    {
        return $user->can(Permissions::DISTRIBUTION_TRANSFERS_RESOLVE)
            && ($this->canOperateSource($user, $transfer) || $this->canOperateDestination($user, $transfer))
            && $transfer->hasOpenDiscrepancies();
    }

    private function canAccess(User $user, StockTransfer $transfer): bool
    {
        if ($user->hasGlobalAccess() || (int) $transfer->requested_by === (int) $user->id) {
            return true;
        }

        return $this->hasWarehouseAccess($user, $transfer->sourceWarehouse, WarehouseAccessLevel::View)
            || $this->hasWarehouseAccess($user, $transfer->destinationWarehouse, WarehouseAccessLevel::View);
    }

    private function canOperateSource(User $user, StockTransfer $transfer): bool
    {
        return $this->hasWarehouseAccess($user, $transfer->sourceWarehouse, WarehouseAccessLevel::Operate);
    }

    private function canOperateDestination(User $user, StockTransfer $transfer): bool
    {
        return $this->hasWarehouseAccess($user, $transfer->destinationWarehouse, WarehouseAccessLevel::Operate);
    }

    private function hasWarehouseAccess(User $user, ?Warehouse $warehouse, WarehouseAccessLevel $level): bool
    {
        return $warehouse !== null && $user->hasWarehouseAccess($warehouse, $level);
    }
}
