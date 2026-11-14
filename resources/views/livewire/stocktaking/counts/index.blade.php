<div class="space-y-6">
    <x-page-header title="Stock Counts" description="Full, cycle, and blind stocktakes. Counters record quantities; controllers analyse variances and post adjustments.">
        <x-slot name="actions">
            @can('create', App\Domain\Stocktaking\Models\StockCount::class)
                <flux:button variant="primary" icon="plus" :href="route('stocktaking.counts.create')" wire:navigate>New stock count</flux:button>
            @endcan
        </x-slot>
    </x-page-header>

    <x-domain-feedback :message="$feedback" :type="$feedbackType" />

    <flux:card>
        <div class="flex flex-wrap items-center gap-3">
            <flux:input wire:model.live.debounce.300ms="search" placeholder="Search number or scope" icon="magnifying-glass" class="max-w-sm" />
            <flux:select wire:model.live="statusFilter" class="max-w-48">
                <option value="">All statuses</option>
                @foreach ($statusOptions as $status)
                    <option value="{{ $status->value }}">{{ $status->label() }}</option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="typeFilter" class="max-w-48">
                <option value="">All types</option>
                @foreach ($typeOptions as $type)
                    <option value="{{ $type->value }}">{{ $type->label() }}</option>
                @endforeach
            </flux:select>
            <flux:spacer />
            <span class="text-sm text-zinc-500 dark:text-zinc-400">{{ $counts->total() }} counts</span>
        </div>

        <flux:table :paginate="$counts" class="mt-4">
            <flux:table.columns>
                <flux:table.column>Number</flux:table.column>
                <flux:table.column>Type</flux:table.column>
                <flux:table.column>Warehouse</flux:table.column>
                <flux:table.column>Scope</flux:table.column>
                <flux:table.column>Snapshot</flux:table.column>
                <flux:table.column align="end">Lines</flux:table.column>
                <flux:table.column align="end">Variances</flux:table.column>
                <flux:table.column>Status</flux:table.column>
                <flux:table.column align="end">Actions</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($counts as $count)
                    <flux:table.row :key="$count->id">
                        <flux:table.cell variant="strong">
                            <a href="{{ route('stocktaking.counts.show', $count) }}" wire:navigate class="hover:underline">{{ $count->document_number }}</a>
                        </flux:table.cell>
                        <flux:table.cell>{{ $count->count_type->label() }}</flux:table.cell>
                        <flux:table.cell>{{ $count->warehouse?->name ?? '—' }}</flux:table.cell>
                        <flux:table.cell class="max-w-64 truncate">{{ $count->scope_description }}</flux:table.cell>
                        <flux:table.cell>{{ $count->snapshot_at?->format('d M Y H:i') ?? 'Not started' }}</flux:table.cell>
                        <flux:table.cell align="end">{{ $count->items_count }}</flux:table.cell>
                        <flux:table.cell align="end">{{ $count->variances_count }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="match ($count->status) {
                                App\Enums\CountStatus::Draft => 'zinc',
                                App\Enums\CountStatus::Counting => 'blue',
                                App\Enums\CountStatus::Recount => 'purple',
                                App\Enums\CountStatus::VarianceReview => 'amber',
                                App\Enums\CountStatus::AdjustmentPending => 'orange',
                                App\Enums\CountStatus::Completed => 'green',
                                App\Enums\CountStatus::Cancelled => 'red',
                            }">{{ $count->status->label() }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            <div class="flex justify-end gap-1">
                                <flux:button size="sm" variant="ghost" icon="eye" aria-label="Open stock count" :href="route('stocktaking.counts.show', $count)" wire:navigate />
                                @can('delete', $count)
                                    <flux:button size="sm" variant="ghost" icon="trash" aria-label="Delete stock count" wire:click="delete({{ $count->id }})" wire:confirm="Delete this draft stock count?" />
                                @endcan
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="9" class="py-10 text-center text-zinc-500 dark:text-zinc-400">
                            No stock counts match your filters.
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>
</div>
