<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum RfqSupplierStatus: string
{
    use EnumHelpers;

    case Invited = 'invited';
    case Responded = 'responded';
    case Declined = 'declined';
    case NoResponse = 'no_response';
}
