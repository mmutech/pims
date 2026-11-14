<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum AdjustmentStatus: string
{
    use EnumHelpers;

    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case Posted = 'posted';
    case Reversed = 'reversed';
    case Cancelled = 'cancelled';

    public function canBeEdited(): bool
    {
        return $this === self::Draft;
    }

    public function canBeSubmitted(): bool
    {
        return $this === self::Draft;
    }

    public function canBePosted(): bool
    {
        return in_array($this, [self::Draft, self::Approved], true);
    }

    public function canBeReversed(): bool
    {
        return $this === self::Posted;
    }

    public function canBeCancelled(): bool
    {
        return in_array($this, [self::Draft, self::PendingApproval, self::Approved], true);
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Posted, self::Reversed, self::Cancelled], true);
    }
}
