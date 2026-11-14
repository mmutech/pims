<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum ApprovalRequestStageStatus: string
{
    use EnumHelpers;

    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Blocked = 'blocked';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Skipped = 'skipped';

    public function isOpen(): bool
    {
        return in_array($this, [self::Pending, self::InProgress, self::Blocked], true);
    }

    public function isFinal(): bool
    {
        return ! $this->isOpen();
    }
}
