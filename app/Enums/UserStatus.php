<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum UserStatus: string
{
    use EnumHelpers;

    case Active = 'active';
    case Suspended = 'suspended';
    case Inactive = 'inactive';

    public function canLogin(): bool
    {
        return $this === self::Active;
    }
}
