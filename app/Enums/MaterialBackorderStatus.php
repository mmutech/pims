<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum MaterialBackorderStatus: string
{
    use EnumHelpers;

    case Open = 'open';
    case Fulfilled = 'fulfilled';
    case Cancelled = 'cancelled';

    public function isOpen(): bool
    {
        return $this === self::Open;
    }
}
