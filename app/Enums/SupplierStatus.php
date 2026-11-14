<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum SupplierStatus: string
{
    use EnumHelpers;

    case Pending = 'pending';
    case Active = 'active';
    case Suspended = 'suspended';
    case Blacklisted = 'blacklisted';
    case Inactive = 'inactive';

    public function canReceiveAwards(): bool
    {
        return $this === self::Active;
    }

    public function isTerminal(): bool
    {
        return $this === self::Blacklisted;
    }
}
