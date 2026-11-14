<div class="space-y-6">
    <x-page-header title="Stock Reconciliations" description="Balancing statements that prove snapshot plus post-snapshot movements plus posted adjustments equals the current ledger.">
        <x-slot name="actions">
            @can('viewAny', App\Domain\Stocktaking\Models\StockCount::class)
                <flux:button variant="ghost" icon="clipboard-document-list" :href="route('stocktaking.counts.index')" wire:navigate>Stock counts</flux:button>
            @endcan
        </x-slot>
    </x-page-header>

    <x-domain-feedback :message="$feedback" :type="$feedbackType" />

    <flux:card>
        <div class="flex flex-wrap items-center gap-3">
            <flux:input wire:model.live.debounce.300ms="search" placeholder="Search reconciliation or count number" icon="magnifying-glass" class="max-w-sm" />
            <flux:select wire:model.live="statusFilter" class="max-w-48">
                <option value="">All statuses</option>
                @foreach ($statusOptions as $status)
                    <option value="{{ $status->value }}">{{ $status->label() }}</option>
                @endforeach
            </flux:select>
            <flux:spacer />
            <span class="text-sm text-zinc-500 dark:text-zinc-400">{{ $reconciliations->total() }} reconciliations</span>
        </div>

        <flux:table :paginate="$reconciliations" class="mt-4">
            <flux:table.columns>
                <flux:table.column>Number</flux:table.column>
                <flux:table.column>Stock count</flux:table.column>
                <flux:table.column>Warehouse</flux:table.column>
                <flux:table.column align="end">Snapshot</flux:table.column>
                <flux:table.column align="end">Movements</flux:table.column>
                <flux:table.column align="end">Adjustments</flux:table.column>
                <flux:table.column align="end">Ledger</flux:table.column>
                <flux:table.column align="end">Difference</flux:table.column>
                <flux:table.column>Status</flux:table.column>
                <flux:table.column align="end">Actions</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($reconciliations as $reconciliation)
                    <flux:table.row :key="$reconciliation->id">
                        <flux:table.cell variant="strong">{{ $reconciliation->document_number }}</flux:table.cell>
                        <flux:table.cell>
                            @if ($reconciliation->stockCount)
                                <a href="{{ route('stocktaking.counts.show', $reconciliation->stockCount) }}" wire:navigate class="hover:underline">{{ $reconciliation->stockCount->document_number }}</a>
                            @else
                                —
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>{{ $reconciliation->warehouse?->name ?? '—' }}</flux:table.cell>
                        <flux:table.cell align="end">{{ rtrim(rtrim($reconciliation->snapshot_quantity, '0'), '.') }}</flux:table.cell>
                        <flux:table.cell align="end">{{ rtrim(rtrim($reconciliation->movement_quantity, '0'), '.') }}</flux:table.cell>
                        <flux:table.cell align="end">{{ rtrim(rtrim($reconciliation->adjustment_quantity, '0'), '.') }}</flux:table.cell>
                        <flux:table.cell align="end">{{ rtrim(rtrim($reconciliation->final_quantity, '0'), '.') }}</flux:table.cell>
                        <flux:table.cell align="end">
                            <span @class([
                                'font-medium',
                                'text-red-600 dark:text-red-400' => (float) $reconciliation->difference_quantity !== 0.0,
                            ])>{{ rtrim(rtrim($reconciliation->difference_quantity, '0'), '.') }}</span>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="match ($reconciliation->status) {
                                App\Enums\ReconciliationStatus::Draft => 'amber',
                                App\Enums\ReconciliationStatus::Approved => 'blue',
                                App\Enums\ReconciliationStatus::Posted => 'green',
                            }">{{ $reconciliation->status->label() }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            @if ($reconciliation->stockCount)
                                <flux:button size="sm" variant="ghost" icon="eye" aria-label="Open stock count" :href="route('stocktaking.counts.show', $reconciliation->stockCount)" wire:navigate />
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="10" class="py-10 text-center text-zinc-500 dark:text-zinc-400">
                            No reconciliations match your filters.
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>
</div>
