<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum PurchaseRequisitionStatus: string
{
    use EnumHelpers;

    case Draft = 'draft';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case PartiallyApproved = 'partially_approved';
    case Rejected = 'rejected';
    case Returned = 'returned';
    case Converted = 'converted';
    case Cancelled = 'cancelled';
}
