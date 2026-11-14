<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum MaterialReturnType: string
{
    use EnumHelpers;

    case Project = 'project';
    case Department = 'department';
    case Supplier = 'supplier';

    public function requiresOriginalIssue(): bool
    {
        return in_array($this, [self::Project, self::Department], true);
    }
}
