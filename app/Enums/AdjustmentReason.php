<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use Illuminate\Support\Str;

/**
 * Controlled reason codes for stock adjustments.
 *
 * Every adjustment must carry one of these codes so variance and audit reports
 * can group corrections consistently.
 */
enum AdjustmentReason: string
{
    use EnumHelpers;

    case Damage = 'damage';
    case Theft = 'theft';
    case Obsolescence = 'obsolescence';
    case CountCorrection = 'count_correction';
    case Reclassification = 'reclassification';
    case OpeningBalance = 'opening_balance';
    case SystemCorrection = 'system_correction';

    public function label(): string
    {
        return match ($this) {
            self::CountCorrection => 'Count correction',
            self::OpeningBalance => 'Opening balance',
            self::SystemCorrection => 'System correction',
            default => Str::headline($this->value),
        };
    }

    /**
     * Reasons that normally decrease the on-hand quantity.
     */
    public function isLossReason(): bool
    {
        return in_array($this, [self::Damage, self::Theft, self::Obsolescence], true);
    }

    public function requiresEvidence(): bool
    {
        return in_array($this, [self::Damage, self::Theft, self::Obsolescence], true);
    }
}
