<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum WarehouseType: string
{
    use EnumHelpers;

    case Central = 'central';
    case Regional = 'regional';
    case BusinessUnit = 'business_unit';
    case Project = 'project';
    case Transit = 'transit';
    case Quarantine = 'quarantine';

    public function isOperational(): bool
    {
        return ! in_array($this, [self::Transit, self::Quarantine], true);
    }
}
