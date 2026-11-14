<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum AdjustmentType: string
{
    use EnumHelpers;

    case Increase = 'increase';
    case Decrease = 'decrease';

    public function direction(): int
    {
        return $this === self::Increase ? 1 : -1;
    }

    public function movementType(): StockMovementType
    {
        return $this === self::Increase
            ? StockMovementType::AdjustmentIn
            : StockMovementType::AdjustmentOut;
    }

    public function isIncrease(): bool
    {
        return $this === self::Increase;
    }

    /**
     * Derive the adjustment type from a signed quantity delta.
     */
    public static function fromDelta(string $delta): self
    {
        return str_starts_with(trim($delta), '-') ? self::Decrease : self::Increase;
    }
}
