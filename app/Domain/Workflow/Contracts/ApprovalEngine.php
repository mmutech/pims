<?php

namespace App\Domain\Workflow\Contracts;

use App\Domain\Workflow\Models\ApprovalRequest;
use App\Enums\ApprovalActionType;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

interface ApprovalEngine
{
    public function submit(Model&Approvable $document, User $actor): ApprovalRequest;

    public function act(
        ApprovalRequest $request,
        User $actor,
        ApprovalActionType $action,
        ?string $comment = null,
        array $metadata = [],
    ): ApprovalRequest;

    public function cancel(ApprovalRequest $request, User $actor, string $reason): ApprovalRequest;

    public function expire(ApprovalRequest $request, string $reason = 'Document validity expired'): ApprovalRequest;

    /**
     * @return Collection<int, ApprovalRequest>
     */
    public function pendingFor(User $user): Collection;

    /**
     * @return Collection<int, ApprovalAction>
     */
    public function historyFor(Model&Approvable $document): Collection;
}
