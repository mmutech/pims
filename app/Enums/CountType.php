<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum CountType: string
{
    use EnumHelpers;

    case Full = 'full';
    case Cycle = 'cycle';
    case Blind = 'blind';

    public function isBlind(): bool
    {
        return $this === self::Blind;
    }

    /**
     * Blind counts must never expose system quantities to the counter.
     */
    public function hidesSystemQuantity(): bool
    {
        return $this->isBlind();
    }
}
