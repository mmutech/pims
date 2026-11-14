<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum UnitOfMeasureType: string
{
    use EnumHelpers;

    case Quantity = 'quantity';
    case Weight = 'weight';
    case Length = 'length';
    case Volume = 'volume';
    case Area = 'area';
    case Time = 'time';
}
