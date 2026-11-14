<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum ReconciliationStatus: string
{
    use EnumHelpers;

    case Draft = 'draft';
    case Approved = 'approved';
    case Posted = 'posted';

    public function canBeApproved(): bool
    {
        return $this === self::Draft;
    }

    public function canBePosted(): bool
    {
        return $this === self::Approved;
    }
}
