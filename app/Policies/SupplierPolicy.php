<?php

namespace App\Policies;

use App\Domain\Supplier\Models\Supplier;
use App\Models\User;
use App\Support\Permissions;

class SupplierPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isSuperAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::SUPPLIERS_VIEW);
    }

    public function view(User $user, Supplier $supplier): bool
    {
        return $user->can(Permissions::SUPPLIERS_VIEW);
    }

    public function create(User $user): bool
    {
        return $user->can(Permissions::SUPPLIERS_CREATE);
    }

    public function update(User $user, Supplier $supplier): bool
    {
        return $user->can(Permissions::SUPPLIERS_UPDATE);
    }

    public function delete(User $user, Supplier $supplier): bool
    {
        return $user->can(Permissions::SUPPLIERS_DELETE);
    }

    public function approve(User $user, Supplier $supplier): bool
    {
        return $user->can(Permissions::SUPPLIERS_APPROVE);
    }
}
