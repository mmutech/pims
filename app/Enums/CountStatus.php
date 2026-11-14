<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum CountStatus: string
{
    use EnumHelpers;

    case Draft = 'draft';
    case Counting = 'counting';
    case Recount = 'recount';
    case VarianceReview = 'variance_review';
    case AdjustmentPending = 'adjustment_pending';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function canBeStarted(): bool
    {
        return $this === self::Draft;
    }

    public function canBeCounted(): bool
    {
        return in_array($this, [self::Counting, self::Recount, self::VarianceReview], true);
    }

    public function canBeAnalysed(): bool
    {
        return in_array($this, [self::Counting, self::Recount, self::VarianceReview], true);
    }

    public function canBeCancelled(): bool
    {
        return ! in_array($this, [self::Completed, self::Cancelled], true);
    }

    public function isOpen(): bool
    {
        return ! in_array($this, [self::Completed, self::Cancelled], true);
    }
}
