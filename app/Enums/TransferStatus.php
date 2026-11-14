<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum TransferStatus: string
{
    use EnumHelpers;

    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case PartiallyDispatched = 'partially_dispatched';
    case InTransit = 'in_transit';
    case PartiallyReceived = 'partially_received';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
