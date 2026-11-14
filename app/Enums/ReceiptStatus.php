<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum ReceiptStatus: string
{
    use EnumHelpers;

    case Draft = 'draft';
    case PendingVerification = 'pending_verification';
    case Verified = 'verified';
    case PendingInspection = 'pending_inspection';
    case Inspected = 'inspected';
    case Posted = 'posted';
    case Reversed = 'reversed';
    case Cancelled = 'cancelled';
}
