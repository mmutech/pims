<div class="space-y-6">
    <x-page-header :title="str($approvalRequest->document_type)->headline().' Approval'" description="Review the routing history and record your decision.">
        <x-slot name="actions">
            <flux:button variant="ghost" icon="arrow-left" :href="route('approvals.index')" wire:navigate>Back to approvals</flux:button>
        </x-slot>
    </x-page-header>

    <x-domain-feedback :message="$feedback" :type="$feedbackType" />

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <flux:card>
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <flux:heading size="lg">{{ str($approvalRequest->document_type)->headline() }}</flux:heading>
                        <flux:subheading>
                            {{ $approvalRequest->metadata['document_number'] ?? 'Document #'.$approvalRequest->document_id }}
                            · requested by {{ $approvalRequest->requester?->name ?? 'unknown user' }}
                        </flux:subheading>
                    </div>
                    <flux:badge size="sm" :color="$statusColors[$approvalRequest->status->value] ?? 'zinc'">
                        {{ $approvalRequest->status->label() }}
                    </flux:badge>
                </div>

                <dl class="mt-5 grid gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Requested</dt>
                        <dd class="mt-1 text-sm text-zinc-800 dark:text-zinc-100">{{ $approvalRequest->requested_at?->format('d M Y, H:i') ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Amount</dt>
                        <dd class="mt-1 font-mono text-sm text-zinc-800 dark:text-zinc-100">
                            {{ ($approvalRequest->metadata['amount'] ?? null) !== null ? number_format((float) $approvalRequest->metadata['amount'], 2).' '.($approvalRequest->metadata['currency'] ?? '') : '—' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Flow</dt>
                        <dd class="mt-1 text-sm text-zinc-800 dark:text-zinc-100">{{ $approvalRequest->flow?->name ?? '—' }} <span class="text-xs text-zinc-500">v{{ $approvalRequest->flow?->version }}</span></dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Validity</dt>
                        <dd class="mt-1 text-sm text-zinc-800 dark:text-zinc-100">{{ $approvalRequest->expires_at?->format('d M Y, H:i') ?? 'No expiry' }}</dd>
                    </div>
                    @if ($approvalRequest->completed_at !== null)
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Completed</dt>
                            <dd class="mt-1 text-sm text-zinc-800 dark:text-zinc-100">{{ $approvalRequest->completed_at->format('d M Y, H:i') }}</dd>
                        </div>
                    @endif
                </dl>
            </flux:card>

            <flux:card>
                <flux:heading size="lg">Approval routing</flux:heading>
                <flux:subheading>Runtime snapshot of the stages captured when this request was submitted.</flux:subheading>

                <div class="mt-5 space-y-4">
                    @foreach ($approvalRequest->stages as $stage)
                        <div class="rounded-lg border p-4 {{ $stage->id === $currentStage?->id ? 'border-navy-300 bg-navy-50/60 dark:border-navy-700 dark:bg-navy-950/30' : 'border-zinc-200 dark:border-zinc-700' }}">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div>
                                    <div class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">
                                        {{ $stage->sequence }}. {{ $stage->name }}
                                    </div>
                                    <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                        {{ $stage->approval_mode->label() }} · {{ $stage->received_approvals }}/{{ $stage->required_approvals }} approvals
                                    </div>
                                </div>
                                <flux:badge size="sm" :color="match ($stage->status) {
                                    App\Enums\ApprovalRequestStageStatus::Approved => 'green',
                                    App\Enums\ApprovalRequestStageStatus::Rejected => 'red',
                                    App\Enums\ApprovalRequestStageStatus::Blocked => 'red',
                                    App\Enums\ApprovalRequestStageStatus::InProgress => 'blue',
                                    App\Enums\ApprovalRequestStageStatus::Skipped => 'zinc',
                                    default => 'amber',
                                }">
                                    {{ $stage->status->label() }}
                                </flux:badge>
                            </div>

                            @if ($stage->blocked_reason)
                                <p class="mt-2 text-xs text-red-600 dark:text-red-400">{{ $stage->blocked_reason }}</p>
                            @endif

                            <div class="mt-3 flex flex-wrap gap-2">
                                @foreach ($stage->assignees as $assignee)
                                    <span class="inline-flex items-center gap-1 rounded-full border border-zinc-200 px-2 py-1 text-xs text-zinc-700 dark:border-zinc-700 dark:text-zinc-300">
                                        {{ $assignee->user?->name ?? 'Unknown user' }}
                                        <span class="text-zinc-400">·</span>
                                        <span class="text-zinc-500 dark:text-zinc-400">{{ $assignee->status->label() }}</span>
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </flux:card>

            <flux:card>
                <flux:heading size="lg">History</flux:heading>
                <flux:subheading>Append-only record of every decision on this request.</flux:subheading>

                <div class="mt-5 space-y-4">
                    @forelse ($actions as $action)
                        <div class="flex gap-3">
                            <div class="mt-1 flex size-8 shrink-0 items-center justify-center rounded-full bg-zinc-100 dark:bg-zinc-800">
                                <flux:icon :name="match ($action->action) {
                                    App\Enums\ApprovalActionType::Approve => 'check',
                                    App\Enums\ApprovalActionType::Reject => 'x-mark',
                                    App\Enums\ApprovalActionType::Return => 'arrow-uturn-left',
                                    App\Enums\ApprovalActionType::Delegate => 'user-plus',
                                    App\Enums\ApprovalActionType::Escalate => 'arrow-trending-up',
                                    App\Enums\ApprovalActionType::Cancel => 'no-symbol',
                                    App\Enums\ApprovalActionType::Expire => 'clock',
                                }" variant="mini" class="size-4 text-zinc-600 dark:text-zinc-300" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2 text-sm">
                                    <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $action->actor?->name ?? 'System' }}</span>
                                    <span class="text-zinc-500 dark:text-zinc-400">{{ $action->action->label() }}</span>
                                    @if ($action->stage)
                                        <span class="text-xs text-zinc-500 dark:text-zinc-400">at {{ $action->stage->name }}</span>
                                    @endif
                                    @if (($action->metadata['override'] ?? false) === true)
                                        <flux:badge size="sm" color="orange">Override</flux:badge>
                                    @endif
                                    @if (($action->metadata['delegated_from'] ?? null) !== null)
                                        <flux:badge size="sm" color="blue">Delegated</flux:badge>
                                    @endif
                                </div>
                                @if ($action->comment)
                                    <p class="mt-1 whitespace-pre-line text-sm text-zinc-600 dark:text-zinc-300">{{ $action->comment }}</p>
                                @endif
                                <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ $action->acted_at?->format('d M Y, H:i') }}</div>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">No actions recorded yet.</p>
                    @endforelse
                </div>
            </flux:card>
        </div>

        <div class="space-y-6">
            @if ($canAct)
                <flux:card>
                    <flux:heading size="lg">Your decision</flux:heading>
                    <flux:subheading>{{ $currentStage?->name }} · {{ $currentStage?->approval_mode->label() }}</flux:subheading>

                    <div class="mt-4 space-y-3">
                        <flux:textarea wire:model="comment" label="Comment" rows="3" description="Required when rejecting or returning." />
                        <flux:error name="comment" />

                        <div class="flex flex-wrap gap-2">
                            <flux:button variant="primary" icon="check" wire:click="approve" wire:confirm="Approve this document?">Approve</flux:button>
                            <flux:button variant="danger" icon="x-mark" wire:click="reject" wire:confirm="Reject this document? This cannot be undone.">Reject</flux:button>
                            <flux:button variant="ghost" icon="arrow-uturn-left" wire:click="returnForCorrection">Return</flux:button>
                        </div>
                    </div>
                </flux:card>
            @elseif ($approvalRequest->isPending() && $currentStage?->status === App\Enums\ApprovalRequestStageStatus::Blocked)
                <flux:card>
                    <flux:heading size="lg">Stage blocked</flux:heading>
                    <flux:subheading class="text-red-600 dark:text-red-400">{{ $currentStage->blocked_reason }}</flux:subheading>
                    <p class="mt-3 text-sm text-zinc-600 dark:text-zinc-300">An administrator must configure an eligible approver or exercise an approved override.</p>
                </flux:card>
            @endif

            @if ($canDelegate || $canCancel)
                <flux:card>
                    <flux:heading size="lg">Request controls</flux:heading>
                    <div class="mt-4 flex flex-wrap gap-2">
                        @if ($canDelegate)
                            <flux:button variant="ghost" icon="user-plus" wire:click="$set('showDelegateModal', true)">Delegate</flux:button>
                        @endif
                        @if ($canCancel)
                            <flux:button variant="ghost" icon="no-symbol" wire:click="$set('showCancelModal', true)">Cancel request</flux:button>
                        @endif
                    </div>
                </flux:card>
            @endif

            @if ($approvalRequest->escalations->isNotEmpty())
                <flux:card>
                    <flux:heading size="lg">Escalations</flux:heading>
                    <div class="mt-3 space-y-3">
                        @foreach ($approvalRequest->escalations as $escalation)
                            <div class="text-sm">
                                <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $escalation->escalatedTo?->name ?? 'Unassigned' }}</div>
                                <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ $escalation->reason }} · {{ $escalation->escalated_at->format('d M Y, H:i') }}</div>
                            </div>
                        @endforeach
                    </div>
                </flux:card>
            @endif
        </div>
    </div>

    <flux:modal wire:model="showCancelModal" class="md:w-[30rem]">
        <form wire:submit="cancelRequest" class="space-y-4">
            <div>
                <flux:heading size="lg">Cancel approval request</flux:heading>
                <flux:subheading>Pending actions will stop immediately. The history is retained.</flux:subheading>
            </div>
            <flux:textarea wire:model="cancelReason" label="Reason" rows="3" required />
            <flux:error name="cancelReason" />
            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showCancelModal', false)">Keep request</flux:button>
                <flux:button type="submit" variant="danger">Cancel request</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model="showDelegateModal" class="md:w-[30rem]">
        <form wire:submit="delegate" class="space-y-4">
            <div>
                <flux:heading size="lg">Delegate approval authority</flux:heading>
                <flux:subheading>The delegate can act on approvals for {{ str($approvalRequest->document_type)->headline() }} during the selected window.</flux:subheading>
            </div>
            <flux:select wire:model="delegateId" label="Delegate" searchable required>
                <option value="">Select a user</option>
                @foreach ($delegates as $delegate)
                    <option value="{{ $delegate->id }}">{{ $delegate->name }}</option>
                @endforeach
            </flux:select>
            <flux:error name="delegateId" />
            <flux:input wire:model="delegationEndsAt" type="date" label="End date" required />
            <flux:error name="delegationEndsAt" />
            <flux:textarea wire:model="delegationReason" label="Reason" rows="2" />
            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showDelegateModal', false)">Cancel</flux:button>
                <flux:button type="submit" variant="primary">Delegate</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
