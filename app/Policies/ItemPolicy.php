<?php

namespace App\Policies;

use App\Domain\Catalog\Models\Item;
use App\Models\User;
use App\Support\Permissions;

class ItemPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isSuperAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::ITEMS_VIEW);
    }

    public function view(User $user, Item $item): bool
    {
        return $user->can(Permissions::ITEMS_VIEW);
    }

    public function create(User $user): bool
    {
        return $user->can(Permissions::ITEMS_CREATE);
    }

    public function update(User $user, Item $item): bool
    {
        return $user->can(Permissions::ITEMS_UPDATE);
    }

    public function delete(User $user, Item $item): bool
    {
        return $user->can(Permissions::ITEMS_DELETE);
    }
}
