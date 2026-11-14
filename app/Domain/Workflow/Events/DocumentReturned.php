<?php

namespace App\Domain\Workflow\Events;

use App\Domain\Workflow\Models\ApprovalRequest;
use Illuminate\Foundation\Events\Dispatchable;

class DocumentReturned
{
    use Dispatchable;

    public function __construct(public ApprovalRequest $request) {}
}
