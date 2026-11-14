<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum ApprovalConditionOperator: string
{
    use EnumHelpers;

    case Equals = 'equals';
    case NotEquals = 'not_equals';
    case In = 'in';
    case NotIn = 'not_in';
    case GreaterThan = 'greater_than';
    case LessThan = 'less_than';
    case Between = 'between';

    public function expectsList(): bool
    {
        return in_array($this, [self::In, self::NotIn, self::Between], true);
    }
}
