<div class="space-y-6">
    <x-page-header title="Stock Adjustments" description="Corrections to on-hand stock. Every posted adjustment moves the ledger through a single controlled posting path.">
        <x-slot name="actions">
            @can('create', App\Domain\Stocktaking\Models\StockAdjustment::class)
                <flux:button variant="primary" icon="plus" :href="route('stocktaking.adjustments.create')" wire:navigate>New adjustment</flux:button>
            @endcan
        </x-slot>
    </x-page-header>

    <x-domain-feedback :message="$feedback" :type="$feedbackType" />

    <flux:card>
        <div class="flex flex-wrap items-center gap-3">
            <flux:input wire:model.live.debounce.300ms="search" placeholder="Search number, reason, or code" icon="magnifying-glass" class="max-w-sm" />
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
            <span class="text-sm text-zinc-500 dark:text-zinc-400">{{ $adjustments->total() }} adjustments</span>
        </div>

        <flux:table :paginate="$adjustments" class="mt-4">
            <flux:table.columns>
                <flux:table.column>Number</flux:table.column>
                <flux:table.column>Type</flux:table.column>
                <flux:table.column>Warehouse</flux:table.column>
                <flux:table.column>Reason</flux:table.column>
                <flux:table.column align="end">Value</flux:table.column>
                <flux:table.column align="end">Lines</flux:table.column>
                <flux:table.column>Status</flux:table.column>
                <flux:table.column align="end">Actions</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($adjustments as $adjustment)
                    <flux:table.row :key="$adjustment->id">
                        <flux:table.cell variant="strong">
                            <a href="{{ route('stocktaking.adjustments.show', $adjustment) }}" wire:navigate class="hover:underline">{{ $adjustment->document_number }}</a>
                        </flux:table.cell>
                        <flux:table.cell>{{ $adjustment->adjustment_type->label() }}</flux:table.cell>
                        <flux:table.cell>{{ $adjustment->warehouse?->name ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $adjustment->reason_code->label() }}</flux:table.cell>
                        <flux:table.cell align="end">{{ number_format((float) $adjustment->total_value, 2) }}</flux:table.cell>
                        <flux:table.cell align="end">{{ $adjustment->items_count }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="match ($adjustment->status) {
                                App\Enums\AdjustmentStatus::Draft => 'zinc',
                                App\Enums\AdjustmentStatus::PendingApproval => 'blue',
                                App\Enums\AdjustmentStatus::Approved => 'amber',
                                App\Enums\AdjustmentStatus::Posted => 'green',
                                App\Enums\AdjustmentStatus::Reversed => 'purple',
                                App\Enums\AdjustmentStatus::Cancelled => 'red',
                            }">{{ $adjustment->status->label() }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            <div class="flex justify-end gap-1">
                                <flux:button size="sm" variant="ghost" icon="eye" aria-label="Open adjustment" :href="route('stocktaking.adjustments.show', $adjustment)" wire:navigate />
                                @can('delete', $adjustment)
                                    <flux:button size="sm" variant="ghost" icon="trash" aria-label="Delete adjustment" wire:click="delete({{ $adjustment->id }})" wire:confirm="Delete this draft stock adjustment?" />
                                @endcan
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="8" class="py-10 text-center text-zinc-500 dark:text-zinc-400">
                            No stock adjustments match your filters.
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>
</div>
