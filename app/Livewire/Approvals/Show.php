<?php

namespace App\Livewire\Approvals;

use App\Domain\Workflow\Contracts\ApprovalEngine;
use App\Domain\Workflow\Models\ApprovalRequest;
use App\Domain\Workflow\Services\ApprovalDelegationService;
use App\Enums\ApprovalActionType;
use App\Enums\ApprovalStatus;
use App\Enums\UserStatus;
use App\Livewire\Concerns\HandlesDomainActions;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Approval Detail')]
class Show extends Component
{
    use HandlesDomainActions;

    public int $approvalId;

    public string $comment = '';

    public bool $showCancelModal = false;

    public string $cancelReason = '';

    public bool $showDelegateModal = false;

    public ?int $delegateId = null;

    public string $delegationEndsAt = '';

    public string $delegationReason = '';

    public function mount(ApprovalRequest $approval): void
    {
        $this->authorize('view', $approval);
        $this->approvalId = $approval->id;
        $this->delegationEndsAt = now()->addDays(7)->format('Y-m-d');
    }

    public function approve(): void
    {
        $this->act(ApprovalActionType::Approve);
    }

    public function reject(): void
    {
        $this->validate(['comment' => ['required', 'string', 'max:2000']], [
            'comment.required' => 'A reason is required when rejecting a document.',
        ]);

        $this->act(ApprovalActionType::Reject);
    }

    public function returnForCorrection(): void
    {
        $this->validate(['comment' => ['required', 'string', 'max:2000']], [
            'comment.required' => 'Explain what the requester needs to correct.',
        ]);

        $this->act(ApprovalActionType::Return);
    }

    public function cancelRequest(): void
    {
        $this->validate(['cancelReason' => ['required', 'string', 'max:2000']]);

        $request = $this->request();
        $this->authorize('cancel', $request);

        $cancelled = $this->runDomainAction(
            fn () => app(ApprovalEngine::class)->cancel($request, auth()->user(), $this->cancelReason),
            'Approval request cancelled.',
        );

        if ($cancelled) {
            $this->showCancelModal = false;
            $this->cancelReason = '';
            $this->comment = '';
        }
    }

    public function delegate(): void
    {
        $this->validate([
            'delegateId' => ['required', 'integer', Rule::exists('users', 'id')],
            'delegationEndsAt' => ['required', 'date', 'after:today'],
            'delegationReason' => ['nullable', 'string', 'max:500'],
        ]);

        $request = $this->request();
        $this->authorize('delegate', $request);

        $delegate = User::findOrFail($this->delegateId);

        $created = $this->runDomainAction(
            fn () => app(ApprovalDelegationService::class)->create(
                auth()->user(),
                $delegate,
                Carbon::now(),
                Carbon::parse($this->delegationEndsAt)->endOfDay(),
                $request->document_type,
                $this->delegationReason !== '' ? $this->delegationReason : null,
                auth()->user(),
            ),
            'Approval authority delegated.',
        );

        if ($created) {
            $this->showDelegateModal = false;
            $this->delegateId = null;
            $this->delegationReason = '';
        }
    }

    public function render()
    {
        $request = $this->request();
        $currentStage = $request->currentStage();
        $user = auth()->user();

        $delegates = User::query()
            ->where('status', UserStatus::Active->value)
            ->whereKeyNot($user->id)
            ->orderBy('name')
            ->get();

        return view('livewire.approvals.show', [
            'approvalRequest' => $request,
            'currentStage' => $currentStage,
            'canAct' => $user->can('act', $request) && $request->isPending() && $currentStage !== null,
            'canCancel' => $user->can('cancel', $request) && $request->isPending(),
            'canDelegate' => $user->can('delegate', $request) && $request->isPending(),
            'delegates' => $delegates,
            'actions' => $request->actions,
            'statusColors' => [
                ApprovalStatus::Pending->value => 'amber',
                ApprovalStatus::Approved->value => 'green',
                ApprovalStatus::Rejected->value => 'red',
                ApprovalStatus::Returned->value => 'orange',
                ApprovalStatus::Cancelled->value => 'zinc',
                ApprovalStatus::Expired->value => 'zinc',
            ],
        ]);
    }

    private function act(ApprovalActionType $action): void
    {
        $request = $this->request();
        $this->authorize('act', $request);

        $acted = $this->runDomainAction(
            fn () => app(ApprovalEngine::class)->act($request, auth()->user(), $action, $this->comment !== '' ? $this->comment : null),
            'Approval decision recorded.',
        );

        if ($acted) {
            $this->comment = '';
        }
    }

    private function request(): ApprovalRequest
    {
        return ApprovalRequest::query()
            ->with([
                'flow',
                'requester',
                'stages.stage',
                'stages.assignees.user',
                'actions.actor',
                'actions.stage',
                'escalations.escalatedTo',
            ])
            ->findOrFail($this->approvalId);
    }
}
