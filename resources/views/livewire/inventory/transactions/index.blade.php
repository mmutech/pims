<div class="space-y-6">
    <x-page-header title="Stock Transactions" description="Append-only record of every posted stock movement." />

    <x-domain-feedback :message="$feedback" :type="$feedbackType" />

    <flux:card>
        <div class="flex flex-wrap items-center gap-3">
            <flux:input wire:model.live.debounce.300ms="search" placeholder="Search transaction number" icon="magnifying-glass" class="max-w-sm" />
            <flux:select wire:model.live="typeFilter" class="max-w-48">
                <option value="">All movement types</option>
                @foreach ($typeOptions as $type)
                    <option value="{{ $type->value }}">{{ $type->label() }}</option>
                @endforeach
            </flux:select>
            <flux:spacer />
            <span class="text-sm text-zinc-500 dark:text-zinc-400">{{ $transactions->total() }} transactions</span>
        </div>

        <flux:table :paginate="$transactions" class="mt-4">
            <flux:table.columns>
                <flux:table.column>Number</flux:table.column>
                <flux:table.column>Type</flux:table.column>
                <flux:table.column>Warehouse</flux:table.column>
                <flux:table.column>Source</flux:table.column>
                <flux:table.column align="end">Lines</flux:table.column>
                <flux:table.column>Status</flux:table.column>
                <flux:table.column>Posted at</flux:table.column>
                <flux:table.column>Posted by</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($transactions as $transaction)
                    <flux:table.row :key="$transaction->id">
                        <flux:table.cell variant="strong">{{ $transaction->transaction_number }}</flux:table.cell>
                        <flux:table.cell>{{ $transaction->transaction_type->label() }}</flux:table.cell>
                        <flux:table.cell>{{ $transaction->warehouse?->name ?? '—' }}</flux:table.cell>
                        <flux:table.cell>
                            <div class="text-xs">{{ $transaction->sourceLabel() }}</div>
                        </flux:table.cell>
                        <flux:table.cell align="end">{{ $transaction->items_count }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$transaction->isReversed() ? 'purple' : 'green'">{{ $transaction->status->label() }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>{{ $transaction->posted_at?->format('d M Y H:i') ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $transaction->postedBy?->name ?? '—' }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="8" class="py-10 text-center text-zinc-500 dark:text-zinc-400">
                            No stock transactions match your filters.
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>
</div>
