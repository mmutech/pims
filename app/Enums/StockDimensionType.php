<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum StockDimensionType: string
{
    use EnumHelpers;

    case Warehouse = 'warehouse';
    case InTransit = 'in_transit';
    case Quarantine = 'quarantine';
    case Damaged = 'damaged';
    case Returns = 'returns';
    case Supplier = 'supplier';

    /**
     * Only physical, usable stock held in a warehouse can satisfy demand.
     */
    public function isAvailableForIssue(): bool
    {
        return $this === self::Warehouse;
    }
}
