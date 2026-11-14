<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum InspectionMethod: string
{
    use EnumHelpers;

    case Visual = 'visual';
    case Dimensional = 'dimensional';
    case Functional = 'functional';
    case Laboratory = 'laboratory';
    case Documentary = 'documentary';
}
