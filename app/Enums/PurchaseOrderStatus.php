<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum PurchaseOrderStatus: string
{
    use EnumHelpers;

    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case PartiallyReceived = 'partially_received';
    case FullyReceived = 'fully_received';
    case Closed = 'closed';
    case Cancelled = 'cancelled';
}
