<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum DeliveryVerificationStatus: string
{
    use EnumHelpers;

    case Pending = 'pending';
    case Verified = 'verified';
    case Discrepancy = 'discrepancy';

    public function isComplete(): bool
    {
        return in_array($this, [self::Verified, self::Discrepancy], true);
    }
}
