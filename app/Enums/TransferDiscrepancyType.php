<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum TransferDiscrepancyType: string
{
    use EnumHelpers;

    case Short = 'short';
    case Damaged = 'damaged';
    case Missing = 'missing';
    case Over = 'over';

    /**
     * Discrepancies that leave stock in transit until they are resolved.
     */
    public function keepsStockInTransit(): bool
    {
        return in_array($this, [self::Short, self::Missing], true);
    }
}
