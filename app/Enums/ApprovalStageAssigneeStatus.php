<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum ApprovalStageAssigneeStatus: string
{
    use EnumHelpers;

    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Skipped = 'skipped';

    public function isPending(): bool
    {
        return $this === self::Pending;
    }
}
