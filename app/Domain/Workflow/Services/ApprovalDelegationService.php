<?php

namespace App\Domain\Workflow\Services;

use App\Domain\Workflow\Models\ApprovalDelegation;
use App\Models\User;
use App\Support\Permissions;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ApprovalDelegationService
{
    public function create(
        User $delegator,
        User $delegate,
        CarbonInterface $startsAt,
        CarbonInterface $endsAt,
        ?string $documentType,
        ?string $reason,
        User $actor,
    ): ApprovalDelegation {
        if ($delegator->id !== $actor->id && ! $actor->can(Permissions::WORKFLOW_OVERRIDE)) {
            throw new AuthorizationException('Only the delegator or a workflow administrator can create this delegation.');
        }

        if (! $actor->can(Permissions::WORKFLOW_DELEGATE)) {
            throw new AuthorizationException('You are not permitted to delegate approval authority.');
        }

        if ($delegator->id === $delegate->id) {
            throw new DomainException('You cannot delegate approval authority to yourself.');
        }

        if (! $delegate->isActive()) {
            throw new DomainException('Approval authority can only be delegated to an active user.');
        }

        if (! $delegate->can(Permissions::WORKFLOW_ACT)) {
            throw new DomainException('The delegate must hold the approval permission.');
        }

        if ($endsAt->lessThanOrEqualTo($startsAt)) {
            throw ValidationException::withMessages([
                'ends_at' => 'The delegation end date must be after its start date.',
            ]);
        }

        $overlaps = ApprovalDelegation::query()
            ->where('delegator_id', $delegator->id)
            ->where('delegate_id', $delegate->id)
            ->where('is_active', true)
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->when($documentType === null, fn ($query) => $query->whereNull('document_type'))
            ->when($documentType !== null, fn ($query) => $query->where('document_type', $documentType))
            ->exists();

        if ($overlaps) {
            throw new DomainException('An overlapping delegation already exists for this delegate.');
        }

        return DB::transaction(fn (): ApprovalDelegation => ApprovalDelegation::create([
            'delegator_id' => $delegator->id,
            'delegate_id' => $delegate->id,
            'document_type' => $documentType,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'reason' => $reason,
            'is_active' => true,
            'created_by' => $actor->id,
        ]));
    }

    public function cancel(ApprovalDelegation $delegation, User $actor): ApprovalDelegation
    {
        if ($delegation->delegator_id !== $actor->id && ! $actor->can(Permissions::WORKFLOW_OVERRIDE)) {
            throw new AuthorizationException('Only the delegator or a workflow administrator can cancel this delegation.');
        }

        $delegation->update(['is_active' => false]);

        return $delegation->refresh();
    }

    public function deactivateExpired(): int
    {
        return ApprovalDelegation::query()
            ->where('is_active', true)
            ->where('ends_at', '<', now())
            ->update(['is_active' => false]);
    }

    public function isActiveFor(ApprovalDelegation $delegation, ?CarbonInterface $at = null): bool
    {
        return $delegation->isActiveAt($at);
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public function defaultWindow(): array
    {
        return [now(), now()->addDays(7)];
    }
}
