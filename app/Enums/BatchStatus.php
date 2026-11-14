<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum BatchStatus: string
{
    use EnumHelpers;

    case Active = 'active';
    case Expired = 'expired';
    case Quarantined = 'quarantined';
    case Depleted = 'depleted';

    public function isAvailable(): bool
    {
        return $this === self::Active;
    }
}
