<?php

namespace App\Policies;

use App\Domain\Catalog\Models\ItemCategory;
use App\Models\User;
use App\Support\Permissions;

class ItemCategoryPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isSuperAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::CATEGORIES_VIEW);
    }

    public function view(User $user, ItemCategory $category): bool
    {
        return $user->can(Permissions::CATEGORIES_VIEW);
    }

    public function create(User $user): bool
    {
        return $user->can(Permissions::CATEGORIES_CREATE);
    }

    public function update(User $user, ItemCategory $category): bool
    {
        return $user->can(Permissions::CATEGORIES_UPDATE);
    }

    public function delete(User $user, ItemCategory $category): bool
    {
        return $user->can(Permissions::CATEGORIES_DELETE);
    }
}
