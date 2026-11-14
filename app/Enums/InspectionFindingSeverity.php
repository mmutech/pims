<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum InspectionFindingSeverity: string
{
    use EnumHelpers;

    case Minor = 'minor';
    case Major = 'major';
    case Critical = 'critical';
}
