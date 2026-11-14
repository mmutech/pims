<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum VarianceResolution: string
{
    use EnumHelpers;

    case Pending = 'pending';
    case Adjusted = 'adjusted';
    case Investigated = 'investigated';
    case Accepted = 'accepted';

    public function isOpen(): bool
    {
        return in_array($this, [self::Pending, self::Investigated], true);
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Adjusted, self::Accepted], true);
    }
}
