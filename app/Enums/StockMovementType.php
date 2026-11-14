<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum StockMovementType: string
{
    use EnumHelpers;

    case Receipt = 'receipt';
    case Issue = 'issue';
    case TransferOut = 'transfer_out';
    case TransferIn = 'transfer_in';
    case AdjustmentIn = 'adjustment_in';
    case AdjustmentOut = 'adjustment_out';
    case ReturnIn = 'return_in';
    case ReturnOut = 'return_out';
    case OpeningBalance = 'opening_balance';
    case ReversalIn = 'reversal_in';
    case ReversalOut = 'reversal_out';

    public function direction(): int
    {
        return match ($this) {
            self::Receipt,
            self::TransferIn,
            self::AdjustmentIn,
            self::ReturnIn,
            self::OpeningBalance,
            self::ReversalIn => 1,
            self::Issue,
            self::TransferOut,
            self::AdjustmentOut,
            self::ReturnOut,
            self::ReversalOut => -1,
        };
    }

    public function isInbound(): bool
    {
        return $this->direction() === 1;
    }
}
