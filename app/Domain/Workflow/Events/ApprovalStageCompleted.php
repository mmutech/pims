<?php

namespace App\Domain\Workflow\Events;

use App\Domain\Workflow\Models\ApprovalRequest;
use App\Domain\Workflow\Models\ApprovalRequestStage;
use Illuminate\Foundation\Events\Dispatchable;

class ApprovalStageCompleted
{
    use Dispatchable;

    public function __construct(
        public ApprovalRequest $request,
        public ApprovalRequestStage $stage,
    ) {}
}
