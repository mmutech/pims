<?php

namespace App\Domain\Workflow\Services;

use App\Domain\Workflow\Contracts\ApprovalEngine;
use App\Domain\Workflow\Models\ApprovalRequest;
use App\Enums\ApprovalStatus;

final class ApprovalExpiryService
{
    public function __construct(private readonly ApprovalEngine $engine) {}

    public function expireRequests(): int
    {
        $count = 0;

        $requests = ApprovalRequest::query()
            ->pending()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->get();

        foreach ($requests as $request) {
            if ($request->status !== ApprovalStatus::Pending) {
                continue;
            }

            $this->engine->expire($request, 'Approval request expired before a decision was made.');
            $count++;
        }

        return $count;
    }
}
