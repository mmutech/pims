<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum InspectionResult: string
{
    use EnumHelpers;

    case Pending = 'pending';
    case Passed = 'passed';
    case Failed = 'failed';
    case PartiallyPassed = 'partially_passed';
}
