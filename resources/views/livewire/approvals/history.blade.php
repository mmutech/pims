<div class="space-y-6">
    <x-page-header title="Approval History" description="Every approval request you raised, approved, or delegated — retained permanently for audit.">
        <x-slot name="actions">
            <flux:button variant="ghost" icon="inbox" :href="route('approvals.index')" wire:navigate>My approvals</flux:button>
        </x-slot>
    </x-page-header>

    <x-domain-feedback :message="$feedback" :type="$feedbackType" />

    <flux:card>
        <div class="flex flex-wrap items-center gap-3">
            <flux:input wire:model.live.debounce.300ms="search" placeholder="Search document or requester" icon="magnifying-glass" class="max-w-sm" />
            <flux:select wire:model.live="statusFilter" class="max-w-40">
                <option value="">All statuses</option>
                @foreach ($statusOptions as $status)
                    <option value="{{ $status->value }}">{{ $status->label() }}</option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="documentTypeFilter" class="max-w-48">
                <option value="">All documents</option>
                @foreach ($documentTypes as $documentType)
                    <option value="{{ $documentType }}">{{ str($documentType)->headline() }}</option>
                @endforeach
            </flux:select>
            @if ($search !== '' || $statusFilter !== '' || $documentTypeFilter !== '')
                <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="clearFilters">Clear</flux:button>
            @endif
            <flux:spacer />
            <span class="text-sm text-zinc-500 dark:text-zinc-400">
                {{ $requests->total() }} {{ $canSeeAll ? 'requests' : 'requests you are involved in' }}
            </span>
        </div>

        <flux:table :paginate="$requests" class="mt-4">
            <flux:table.columns>
                <flux:table.column>Document</flux:table.column>
                <flux:table.column>Requester</flux:table.column>
                <flux:table.column>Status</flux:table.column>
                <flux:table.column>Current stage</flux:table.column>
                <flux:table.column>Requested</flux:table.column>
                <flux:table.column>Completed</flux:table.column>
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
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$statusColors[$approvalRequest->status->value] ?? 'zinc'">
                                {{ $approvalRequest->status->label() }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($approvalRequest->isPending())
                                {{ $stage?->name ?? '—' }}
                                @if ($stage !== null)
                                    <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                        {{ $stage->received_approvals }}/{{ $stage->required_approvals }} approvals
                                    </div>
                                @endif
                            @else
                                —
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>{{ $approvalRequest->requested_at?->format('d M Y, H:i') ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $approvalRequest->completed_at?->format('d M Y, H:i') ?? '—' }}</flux:table.cell>
                        <flux:table.cell align="end">
                            <flux:button size="sm" variant="ghost" icon="eye" :href="route('approvals.show', $approvalRequest)" wire:navigate aria-label="View approval" />
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7" class="py-10 text-center text-zinc-500 dark:text-zinc-400">
                            No approval history matches the current filters.
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>
</div>
