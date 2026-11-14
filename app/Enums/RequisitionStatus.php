<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum RequisitionStatus: string
{
    use EnumHelpers;

    case Draft = 'draft';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case PartiallyApproved = 'partially_approved';
    case Rejected = 'rejected';
    case Returned = 'returned';
    case Reserved = 'reserved';
    case PartiallyIssued = 'partially_issued';
    case Issued = 'issued';
    case Acknowledged = 'acknowledged';
    case Closed = 'closed';
    case Cancelled = 'cancelled';
}
