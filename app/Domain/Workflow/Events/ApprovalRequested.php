<?php

namespace App\Domain\Workflow\Events;

use App\Domain\Workflow\Models\ApprovalRequest;
use Illuminate\Foundation\Events\Dispatchable;

class ApprovalRequested
{
    use Dispatchable;

    public function __construct(public ApprovalRequest $request) {}
}
