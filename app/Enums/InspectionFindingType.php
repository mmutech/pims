<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum InspectionFindingType: string
{
    use EnumHelpers;

    case Packaging = 'packaging';
    case Specification = 'specification';
    case Dimension = 'dimension';
    case Functionality = 'functionality';
    case Documentation = 'documentation';
    case Other = 'other';
}
