<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum QuotationEvaluationStatus: string
{
    use EnumHelpers;

    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Returned = 'returned';
    case Cancelled = 'cancelled';
}
