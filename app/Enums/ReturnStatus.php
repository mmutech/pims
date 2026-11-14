<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum ReturnStatus: string
{
    use EnumHelpers;

    case Draft = 'draft';
    case PendingInspection = 'pending_inspection';
    case Assessed = 'assessed';
    case Posted = 'posted';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
}
