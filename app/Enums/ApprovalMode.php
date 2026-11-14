<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum ApprovalMode: string
{
    use EnumHelpers;

    case Sequential = 'sequential';
    case Parallel = 'parallel';
    case AnyOne = 'any_one';
    case All = 'all';
}
