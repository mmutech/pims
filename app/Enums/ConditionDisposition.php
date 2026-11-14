<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum ConditionDisposition: string
{
    use EnumHelpers;

    case ReturnToStock = 'return_to_stock';
    case Quarantine = 'quarantine';
    case Repair = 'repair';
    case Dispose = 'dispose';
    case ReturnToSupplier = 'return_to_supplier';

    public function addsStock(): bool
    {
        return in_array($this, [self::ReturnToStock, self::Quarantine, self::Repair], true);
    }

    public function targetDimensionType(): ?StockDimensionType
    {
        return match ($this) {
            self::ReturnToStock => StockDimensionType::Warehouse,
            self::Quarantine, self::Repair => StockDimensionType::Quarantine,
            self::Dispose, self::ReturnToSupplier => null,
        };
    }
}
