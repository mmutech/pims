<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum ItemTrackingType: string
{
    use EnumHelpers;

    case None = 'none';
    case Batch = 'batch';
    case Serial = 'serial';
    case BatchSerial = 'batch_serial';

    public function requiresBatch(): bool
    {
        return in_array($this, [self::Batch, self::BatchSerial], true);
    }

    public function requiresSerial(): bool
    {
        return in_array($this, [self::Serial, self::BatchSerial], true);
    }
}
