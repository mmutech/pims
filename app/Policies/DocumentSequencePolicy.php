<?php

namespace App\Policies;

use App\Domain\Administration\Models\DocumentSequence;
use App\Models\User;
use App\Support\Permissions;

class DocumentSequencePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isSuperAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::ADMIN_SEQUENCES_VIEW);
    }

    public function view(User $user, DocumentSequence $sequence): bool
    {
        return $user->can(Permissions::ADMIN_SEQUENCES_VIEW);
    }

    public function update(User $user, DocumentSequence $sequence): bool
    {
        return $user->can(Permissions::ADMIN_SEQUENCES_UPDATE);
    }
}
