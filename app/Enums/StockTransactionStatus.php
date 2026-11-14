<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum StockTransactionStatus: string
{
    use EnumHelpers;

    case Posted = 'posted';
    case Reversed = 'reversed';
}
