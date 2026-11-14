<?php

namespace App\Policies;

use App\Domain\Procurement\Models\PurchaseOrder;
use App\Enums\WarehouseAccessLevel;
use App\Models\User;
use App\Support\Permissions;

class PurchaseOrderPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isSuperAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::PROCUREMENT_ORDERS_VIEW);
    }

    public function view(User $user, PurchaseOrder $order): bool
    {
        return $user->can(Permissions::PROCUREMENT_ORDERS_VIEW)
            && $this->canAccess($user, $order);
    }

    public function create(User $user): bool
    {
        return $user->can(Permissions::PROCUREMENT_ORDERS_CREATE);
    }

    public function update(User $user, PurchaseOrder $order): bool
    {
        return $user->can(Permissions::PROCUREMENT_ORDERS_UPDATE)
            && $this->canAccess($user, $order)
            && $order->canBeEdited();
    }

    public function delete(User $user, PurchaseOrder $order): bool
    {
        return $user->can(Permissions::PROCUREMENT_ORDERS_DELETE)
            && $this->canAccess($user, $order)
            && $order->canBeEdited();
    }

    public function submit(User $user, PurchaseOrder $order): bool
    {
        return $user->can(Permissions::PROCUREMENT_ORDERS_UPDATE)
            && $this->canAccess($user, $order)
            && $order->canBeSubmitted();
    }

    public function cancel(User $user, PurchaseOrder $order): bool
    {
        return $user->can(Permissions::PROCUREMENT_ORDERS_CANCEL)
            && $this->canAccess($user, $order)
            && $order->canBeCancelled();
    }

    public function amend(User $user, PurchaseOrder $order): bool
    {
        return $user->can(Permissions::PROCUREMENT_ORDERS_AMEND)
            && $this->canAccess($user, $order)
            && $order->canBeAmended();
    }

    public function close(User $user, PurchaseOrder $order): bool
    {
        return $user->can(Permissions::PROCUREMENT_ORDERS_CLOSE)
            && $this->canAccess($user, $order)
            && $order->canBeClosed();
    }

    private function canAccess(User $user, PurchaseOrder $order): bool
    {
        if ($user->hasGlobalAccess() || $user->id === $order->created_by) {
            return true;
        }

        return $order->deliveryWarehouse !== null
            && $user->hasWarehouseAccess($order->deliveryWarehouse, WarehouseAccessLevel::View);
    }
}
