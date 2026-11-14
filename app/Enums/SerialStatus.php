<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum SerialStatus: string
{
    use EnumHelpers;

    case Available = 'available';
    case Reserved = 'reserved';
    case Issued = 'issued';
    case InTransit = 'in_transit';
    case Returned = 'returned';
    case Damaged = 'damaged';
    case Scrapped = 'scrapped';

    public function isAvailable(): bool
    {
        return $this === self::Available;
    }
}
