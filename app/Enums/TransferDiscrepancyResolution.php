<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum TransferDiscrepancyResolution: string
{
    use EnumHelpers;

    case Pending = 'pending';
    case WriteOff = 'write_off';
    case ReturnToSource = 'return_to_source';
    case Claim = 'claim';
    case Replacement = 'replacement';

    public function isPending(): bool
    {
        return $this === self::Pending;
    }
}
