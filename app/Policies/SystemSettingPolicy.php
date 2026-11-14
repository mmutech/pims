<?php

namespace App\Policies;

use App\Domain\Administration\Models\SystemSetting;
use App\Models\User;
use App\Support\Permissions;

class SystemSettingPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isSuperAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::ADMIN_SETTINGS_VIEW);
    }

    public function view(User $user, SystemSetting $setting): bool
    {
        return $user->can(Permissions::ADMIN_SETTINGS_VIEW);
    }

    public function update(User $user, SystemSetting $setting): bool
    {
        return $user->can(Permissions::ADMIN_SETTINGS_UPDATE);
    }
}
