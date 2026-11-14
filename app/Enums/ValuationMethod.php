<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum ValuationMethod: string
{
    use EnumHelpers;

    case WeightedAverage = 'weighted_average';
    case Fifo = 'fifo';
}
