<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum VarianceType: string
{
    use EnumHelpers;

    case Gain = 'gain';
    case Loss = 'loss';
    case Misplacement = 'misplacement';
    case RecordingError = 'recording_error';

    public static function fromDelta(string $delta): self
    {
        if (str_starts_with(trim($delta), '-')) {
            return self::Loss;
        }

        return self::Gain;
    }

    public function isGain(): bool
    {
        return $this === self::Gain;
    }

    /**
     * Direction of the corrective stock movement: +1 adds stock, -1 removes it.
     */
    public function direction(): int
    {
        return $this->isGain() ? 1 : -1;
    }
}
