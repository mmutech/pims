<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum ApprovalStatus: string
{
    use EnumHelpers;

    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Returned = 'returned';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    public function isFinal(): bool
    {
        return $this !== self::Pending;
    }
}
