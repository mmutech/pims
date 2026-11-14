<div class="space-y-6">
    <x-page-header title="Stock Issues" description="Issue reserved material to recipients and capture acknowledgement of receipt.">
        <x-slot name="actions">
            @can('create', App\Domain\Distribution\Models\StockIssue::class)
                <flux:button variant="primary" icon="plus" :href="route('distribution.requisitions.index')" wire:navigate>Issue from requisition</flux:button>
            @endcan
        </x-slot>
    </x-page-header>

    <x-domain-feedback :message="$feedback" :type="$feedbackType" />

    <flux:card>
        <div class="flex flex-wrap items-center gap-3">
            <flux:input wire:model.live.debounce.300ms="search" placeholder="Search issue or requisition number" icon="magnifying-glass" class="max-w-sm" />
            <flux:select wire:model.live="statusFilter" class="max-w-48">
                <option value="">All statuses</option>
                @foreach ($statusOptions as $status)
                    <option value="{{ $status->value }}">{{ $status->label() }}</option>
                @endforeach
            </flux:select>
            <flux:spacer />
            <span class="text-sm text-zinc-500 dark:text-zinc-400">{{ $issues->total() }} issues</span>
        </div>

        <flux:table :paginate="$issues" class="mt-4">
            <flux:table.columns>
                <flux:table.column>Number</flux:table.column>
                <flux:table.column>Requisition</flux:table.column>
                <flux:table.column>Recipient</flux:table.column>
                <flux:table.column>Warehouse</flux:table.column>
                <flux:table.column>Issued</flux:table.column>
                <flux:table.column>Acknowledged</flux:table.column>
                <flux:table.column>Status</flux:table.column>
                <flux:table.column align="end">Actions</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($issues as $issue)
                    <flux:table.row :key="$issue->id">
                        <flux:table.cell variant="strong">
                            <a href="{{ route('distribution.issues.show', $issue) }}" wire:navigate class="hover:underline">{{ $issue->document_number }}</a>
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($issue->requisition)
                                <a href="{{ route('distribution.requisitions.show', $issue->requisition) }}" wire:navigate class="hover:underline">{{ $issue->requisition->document_number }}</a>
                            @else
                                —
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>{{ $issue->recipient?->name ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $issue->warehouse?->name ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $issue->issue_date?->format('d M Y') ?? '—' }}</flux:table.cell>
                        <flux:table.cell>
                            @if ($issue->acknowledgement)
                                <flux:badge size="sm" color="green">{{ $issue->acknowledgement->acknowledged_at?->format('d M Y') }}</flux:badge>
                            @else
                                <span class="text-zinc-500 dark:text-zinc-400">Pending</span>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="match ($issue->status) {
                                App\Enums\IssueStatus::Draft => 'zinc',
                                App\Enums\IssueStatus::Posted => 'green',
                                App\Enums\IssueStatus::Reversed => 'red',
                                App\Enums\IssueStatus::Cancelled => 'zinc',
                            }">{{ $issue->status->label() }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            <div class="flex justify-end gap-1">
                                <flux:button size="sm" variant="ghost" icon="eye" aria-label="Open issue" :href="route('distribution.issues.show', $issue)" wire:navigate />
                                @can('delete', $issue)
                                    <flux:button size="sm" variant="ghost" icon="trash" aria-label="Delete issue" wire:click="delete({{ $issue->id }})" wire:confirm="Delete this draft issue?" />
                                @endcan
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="8" class="py-10 text-center text-zinc-500 dark:text-zinc-400">
                            No stock issues match your filters.
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>
</div>
