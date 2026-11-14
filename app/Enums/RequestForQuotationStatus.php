<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum RequestForQuotationStatus: string
{
    use EnumHelpers;

    case Draft = 'draft';
    case Issued = 'issued';
    case Closed = 'closed';
    case Awarded = 'awarded';
    case Cancelled = 'cancelled';

    public function isOpen(): bool
    {
        return in_array($this, [self::Issued, self::Closed], true);
    }

    public function canReceiveQuotations(): bool
    {
        return $this->isOpen();
    }
}
