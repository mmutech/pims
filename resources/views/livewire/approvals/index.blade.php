<div class="space-y-6">
    <x-page-header title="My Approvals" description="Documents currently awaiting your decision. Approvals cannot be actioned in bulk." />

    <x-domain-feedback :message="$feedback" :type="$feedbackType" />

    <div class="grid gap-4 sm:grid-cols-3">
        <flux:card>
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">Awaiting you</flux:text>
            <div class="mt-1 text-2xl font-semibold text-zinc-900 dark:text-zinc-100">{{ $requests->total() }}</div>
        </flux:card>
        <flux:card>
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">Overdue</flux:text>
            <div class="mt-1 text-2xl font-semibold {{ $overdueCount > 0 ? 'text-orange-600 dark:text-orange-400' : 'text-zinc-900 dark:text-zinc-100' }}">{{ $overdueCount }}</div>
        </flux:card>
        <flux:card>
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">Blocked stages</flux:text>
            <div class="mt-1 text-2xl font-semibold {{ $blockedCount > 0 ? 'text-red-600 dark:text-red-400' : 'text-zinc-900 dark:text-zinc-100' }}">{{ $blockedCount }}</div>
        </flux:card>
    </div>

    <flux:card>
        <div class="flex flex-wrap items-center gap-3">
            <flux:input wire:model.live.debounce.300ms="search" placeholder="Search document, number, or requester" icon="magnifying-glass" class="max-w-sm" />
            <flux:spacer />
            <span class="text-sm text-zinc-500 dark:text-zinc-400">{{ $requests->total() }} awaiting your decision</span>
        </div>

        <flux:table :paginate="$requests" class="mt-4">
            <flux:table.columns>
                <flux:table.column>Document</flux:table.column>
                <flux:table.column>Requester</flux:table.column>
                <flux:table.column>Stage</flux:table.column>
                <flux:table.column>Amount</flux:table.column>
                <flux:table.column>Age</flux:table.column>
                <flux:table.column>SLA</flux:table.column>
                <flux:table.column align="end">Actions</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($requests as $approvalRequest)
                    @php($stage = $approvalRequest->currentStage())
                    <flux:table.row :key="$approvalRequest->id">
                        <flux:table.cell variant="strong">
                            <a href="{{ route('approvals.show', $approvalRequest) }}" wire:navigate class="hover:underline">
                                {{ str($approvalRequest->document_type)->headline() }}
                            </a>
                            <div class="text-xs font-normal text-zinc-500 dark:text-zinc-400">
                                {{ $approvalRequest->metadata['document_number'] ?? 'Document #'.$approvalRequest->document_id }}
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>{{ $approvalRequest->requester?->name ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $stage?->name ?? '—' }}</flux:table.cell>
                        <flux:table.cell>
                            @if (($approvalRequest->metadata['amount'] ?? null) !== null)
                                <span class="font-mono text-xs">{{ number_format((float) $approvalRequest->metadata['amount'], 2) }}</span>
                            @else
                                —
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>{{ $approvalRequest->requested_at?->diffForHumans() ?? '—' }}</flux:table.cell>
                        <flux:table.cell>
                            @if ($stage?->status === App\Enums\ApprovalRequestStageStatus::Blocked)
                                <flux:badge size="sm" color="red">Blocked</flux:badge>
                            @elseif ($stage?->isOverdue())
                                <flux:badge size="sm" color="orange">Overdue</flux:badge>
                            @elseif ($stage?->sla_due_at !== null)
                                <flux:badge size="sm" color="zinc">Due {{ $stage->sla_due_at->diffForHumans() }}</flux:badge>
                            @else
                                <flux:badge size="sm" color="zinc">No SLA</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            <flux:button size="sm" variant="ghost" icon="eye" :href="route('approvals.show', $approvalRequest)" wire:navigate aria-label="Review approval" />
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7" class="py-10 text-center text-zinc-500 dark:text-zinc-400">
                            Nothing is awaiting your decision.
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>
</div>
