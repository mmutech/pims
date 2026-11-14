<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum StockReservationStatus: string
{
    use EnumHelpers;

    case Active = 'active';
    case PartiallyConsumed = 'partially_consumed';
    case Consumed = 'consumed';
    case Released = 'released';
    case Expired = 'expired';

    public function isOpen(): bool
    {
        return in_array($this, [self::Active, self::PartiallyConsumed], true);
    }
}
