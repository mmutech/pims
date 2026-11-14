<div class="space-y-6">
    <x-page-header title="Activity Log" description="Immutable audit trail of changes across the system.">
        <x-slot name="actions">
            <flux:button variant="ghost" icon="arrow-path" wire:click="clearFilters">Clear filters</flux:button>
        </x-slot>
    </x-page-header>

    <x-domain-feedback :message="$feedback" :type="$feedbackType" />

    <flux:card>
        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
            <flux:input wire:model.live.debounce.300ms="search" placeholder="Search description or event" icon="magnifying-glass" />
            <flux:select wire:model.live="logName">
                <option value="">All logs</option>
                @foreach ($logNames as $name)
                    <option value="{{ $name }}">{{ str($name)->headline() }}</option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="subjectType">
                <option value="">All subjects</option>
                @foreach ($subjectTypes as $type)
                    <option value="{{ $type }}">{{ $subjectLabel($type) }}</option>
                @endforeach
            </flux:select>
            <flux:input wire:model.live="dateFrom" type="date" label="" />
            <flux:input wire:model.live="dateTo" type="date" label="" />
        </div>

        <flux:table :paginate="$activities" class="mt-4">
            <flux:table.columns>
                <flux:table.column>When</flux:table.column>
                <flux:table.column>Log</flux:table.column>
                <flux:table.column>Description</flux:table.column>
                <flux:table.column>Subject</flux:table.column>
                <flux:table.column>Causer</flux:table.column>
                <flux:table.column>Event</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($activities as $activity)
                    <flux:table.row :key="$activity->id">
                        <flux:table.cell>
                            <div>{{ $activity->created_at?->format('d M Y') }}</div>
                            <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ $activity->created_at?->format('H:i:s') }}</div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" color="zinc">{{ str($activity->log_name ?? 'default')->headline() }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>{{ $activity->description }}</flux:table.cell>
                        <flux:table.cell>
                            <div>{{ $subjectLabel($activity->subject_type) }}</div>
                            @if ($activity->subject_id)
                                <div class="text-xs text-zinc-500 dark:text-zinc-400">#{{ $activity->subject_id }}</div>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            {{ $activity->causer?->name ?? 'System' }}
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($activity->event)
                                <flux:badge size="sm" color="zinc">{{ $activity->event }}</flux:badge>
                            @else
                                <span class="text-xs text-zinc-500 dark:text-zinc-400">—</span>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6" class="py-10 text-center text-zinc-500 dark:text-zinc-400">
                            No activity matches your filters.
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>
</div>
