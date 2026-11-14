<div class="space-y-6">
    <x-page-header title="Delegations" description="Temporary transfer of approval authority for a date window. Delegates must hold the approval permission.">
        <x-slot name="actions">
            @if ($canCreate)
                <flux:button variant="primary" icon="plus" wire:click="openCreate">Delegate authority</flux:button>
            @endif
        </x-slot>
    </x-page-header>

    <x-domain-feedback :message="$feedback" :type="$feedbackType" />

    <flux:card>
        <div class="flex flex-wrap items-center gap-3">
            <flux:input wire:model.live.debounce.300ms="search" placeholder="Search delegator, delegate, or document type" icon="magnifying-glass" class="max-w-sm" />
            <flux:select wire:model.live="statusFilter" class="max-w-40">
                <option value="">All statuses</option>
                <option value="active">Active</option>
                <option value="scheduled">Scheduled</option>
                <option value="ended">Ended</option>
            </flux:select>
            <flux:spacer />
            <span class="text-sm text-zinc-500 dark:text-zinc-400">
                {{ $delegations->total() }} {{ $canSeeAll ? 'delegations' : 'delegations involving you' }}
            </span>
        </div>

        <flux:table :paginate="$delegations" class="mt-4">
            <flux:table.columns>
                <flux:table.column>Delegator</flux:table.column>
                <flux:table.column>Delegate</flux:table.column>
                <flux:table.column>Scope</flux:table.column>
                <flux:table.column>Window</flux:table.column>
                <flux:table.column>Status</flux:table.column>
                <flux:table.column>Reason</flux:table.column>
                <flux:table.column align="end">Actions</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($delegations as $delegation)
                    @php
                        $state = ! $delegation->is_active || $delegation->ends_at->isPast()
                            ? 'ended'
                            : ($delegation->starts_at->isFuture() ? 'scheduled' : 'active');
                    @endphp
                    <flux:table.row :key="$delegation->id">
                        <flux:table.cell variant="strong">{{ $delegation->delegator?->name ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $delegation->delegate?->name ?? '—' }}</flux:table.cell>
                        <flux:table.cell>
                            @if ($delegation->document_type)
                                <span class="font-mono text-xs">{{ $delegation->document_type }}</span>
                            @else
                                <span class="text-zinc-500 dark:text-zinc-400">All documents</span>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="text-sm">{{ $delegation->starts_at->format('d M Y') }}</div>
                            <div class="text-xs text-zinc-500 dark:text-zinc-400">to {{ $delegation->ends_at->format('d M Y') }}</div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="match ($state) {
                                'active' => 'green',
                                'scheduled' => 'blue',
                                default => 'zinc',
                            }">
                                {{ ucfirst($state) }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="max-w-xs truncate text-sm">{{ $delegation->reason ?: '—' }}</div>
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            @can('delete', $delegation)
                                @if ($delegation->is_active && ! $delegation->ends_at->isPast())
                                    <flux:button size="sm" variant="ghost" icon="x-circle" wire:click="cancelDelegation({{ $delegation->id }})" wire:confirm="Cancel this delegation immediately?" aria-label="Cancel delegation" />
                                @else
                                    <span class="text-xs text-zinc-400">—</span>
                                @endif
                            @endcan
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7" class="py-10 text-center text-zinc-500 dark:text-zinc-400">
                            No delegations recorded.
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <flux:modal wire:model="showForm" class="md:w-[34rem]">
        <form wire:submit="save" class="space-y-4">
            <div>
                <flux:heading size="lg">Delegate approval authority</flux:heading>
                <flux:subheading>You remain accountable for decisions made under delegation; every action records the original assignee.</flux:subheading>
            </div>

            <flux:select wire:model="delegateId" label="Delegate" searchable required>
                <option value="">Select a user</option>
                @foreach ($delegates as $delegate)
                    <option value="{{ $delegate->id }}">{{ $delegate->name }}</option>
                @endforeach
            </flux:select>
            <flux:error name="delegateId" />

            <flux:select wire:model="documentType" label="Document type">
                <option value="">All documents</option>
                @foreach ($documentTypes as $type)
                    <option value="{{ $type }}">{{ str($type)->headline() }}</option>
                @endforeach
            </flux:select>
            <flux:error name="documentType" />

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <flux:input wire:model="startsAt" type="date" label="Starts" required />
                    <flux:error name="startsAt" />
                </div>
                <div>
                    <flux:input wire:model="endsAt" type="date" label="Ends" required />
                    <flux:error name="endsAt" />
                </div>
            </div>

            <flux:textarea wire:model="reason" label="Reason" rows="2" placeholder="e.g. Annual leave cover" />
            <flux:error name="reason" />

            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showForm', false)">Cancel</flux:button>
                <flux:button type="submit" variant="primary">Delegate</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
