<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum ApprovalActionType: string
{
    use EnumHelpers;

    case Approve = 'approve';
    case Reject = 'reject';
    case Return = 'return';
    case Delegate = 'delegate';
    case Escalate = 'escalate';
    case Cancel = 'cancel';
    case Expire = 'expire';
}
