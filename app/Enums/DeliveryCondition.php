<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum DeliveryCondition: string
{
    use EnumHelpers;

    case Good = 'good';
    case Damaged = 'damaged';
    case Short = 'short';
    case Over = 'over';

    /**
     * Goods that are physically present but not fit for stock.
     */
    public function isRejected(): bool
    {
        return $this === self::Damaged;
    }
}
