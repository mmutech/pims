<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum MaterialCondition: string
{
    use EnumHelpers;

    case Good = 'good';
    case Damaged = 'damaged';
    case Defective = 'defective';
    case Quarantined = 'quarantined';
    case Repairable = 'repairable';
    case DisposalPending = 'disposal_pending';

    /**
     * Only good-condition returns may re-enter available stock.
     */
    public function isAvailableForStock(): bool
    {
        return $this === self::Good;
    }

    public function requiresQuarantine(): bool
    {
        return in_array($this, [
            self::Damaged,
            self::Defective,
            self::Quarantined,
            self::Repairable,
        ], true);
    }
}
