<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum IssueStatus: string
{
    use EnumHelpers;

    case Draft = 'draft';
    case Posted = 'posted';
    case Reversed = 'reversed';
    case Cancelled = 'cancelled';
}
