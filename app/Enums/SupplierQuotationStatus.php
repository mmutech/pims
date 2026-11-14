<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum SupplierQuotationStatus: string
{
    use EnumHelpers;

    case Draft = 'draft';
    case Submitted = 'submitted';
    case Withdrawn = 'withdrawn';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
}
