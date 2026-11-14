<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum WarehouseLocationType: string
{
    use EnumHelpers;

    case Storage = 'storage';
    case Receiving = 'receiving';
    case Quarantine = 'quarantine';
    case InTransit = 'in_transit';
    case Damaged = 'damaged';
    case Returns = 'returns';

    public function isAvailableForIssue(): bool
    {
        return $this === self::Storage;
    }
}
