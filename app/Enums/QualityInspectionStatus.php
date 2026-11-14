<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum QualityInspectionStatus: string
{
    use EnumHelpers;

    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Passed = 'passed';
    case Failed = 'failed';
    case PartiallyPassed = 'partially_passed';
}
