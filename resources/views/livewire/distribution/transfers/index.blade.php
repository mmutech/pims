<div class="space-y-6">
    <x-page-header title="Stock Transfers" description="Move stock between warehouses with in-transit tracking, discrepancy reconciliation, and approval controls.">
        <x-slot name="actions">
            @can('create', App\Domain\Distribution\Models\StockTransfer::class)
                <flux:button variant="primary" icon="plus" :href="route('distribution.transfers.create')" wire:navigate>New transfer</flux:button>
            @endcan
        </x-slot>
    </x-page-header>

    <x-domain-feedback :message="$feedback" :type="$feedbackType" />

    <flux:card>
        <div class="flex flex-wrap items-center gap-3">
            <flux:input wire:model.live.debounce.300ms="search" placeholder="Search number or reason" icon="magnifying-glass" class="max-w-sm" />
            <flux:select wire:model.live="statusFilter" class="max-w-48">
                <option value="">All statuses</option>
                @foreach ($statusOptions as $status)
                    <option value="{{ $status->value }}">{{ $status->label() }}</option>
                @endforeach
            </flux:select>
            <flux:spacer />
            <span class="text-sm text-zinc-500 dark:text-zinc-400">{{ $transfers->total() }} transfers</span>
        </div>

        <flux:table :paginate="$transfers" class="mt-4">
            <flux:table.columns>
                <flux:table.column>Number</flux:table.column>
                <flux:table.column>From</flux:table.column>
                <flux:table.column>To</flux:table.column>
                <flux:table.column>Requested by</flux:table.column>
                <flux:table.column>Required by</flux:table.column>
                <flux:table.column align="end">Lines</flux:table.column>
                <flux:table.column>Status</flux:table.column>
                <flux:table.column align="end">Actions</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($transfers as $transfer)
                    <flux:table.row :key="$transfer->id">
                        <flux:table.cell variant="strong">
                            <a href="{{ route('distribution.transfers.show', $transfer) }}" wire:navigate class="hover:underline">{{ $transfer->document_number }}</a>
                        </flux:table.cell>
                        <flux:table.cell>{{ $transfer->sourceWarehouse?->name ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $transfer->destinationWarehouse?->name ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $transfer->requester?->name ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $transfer->required_by?->format('d M Y') ?? '—' }}</flux:table.cell>
                        <flux:table.cell align="end">{{ $transfer->items_count }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="match ($transfer->status) {
                                App\Enums\TransferStatus::Draft => 'zinc',
                                App\Enums\TransferStatus::PendingApproval => 'blue',
                                App\Enums\TransferStatus::Approved => 'green',
                                App\Enums\TransferStatus::PartiallyDispatched => 'amber',
                                App\Enums\TransferStatus::InTransit => 'purple',
                                App\Enums\TransferStatus::PartiallyReceived => 'purple',
                                App\Enums\TransferStatus::Completed => 'green',
                                App\Enums\TransferStatus::Cancelled => 'zinc',
                            }">{{ $transfer->status->label() }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            <div class="flex justify-end gap-1">
                                <flux:button size="sm" variant="ghost" icon="eye" aria-label="Open transfer" :href="route('distribution.transfers.show', $transfer)" wire:navigate />
                                @can('delete', $transfer)
                                    <flux:button size="sm" variant="ghost" icon="trash" aria-label="Delete transfer" wire:click="delete({{ $transfer->id }})" wire:confirm="Delete this draft transfer?" />
                                @endcan
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="8" class="py-10 text-center text-zinc-500 dark:text-zinc-400">
                            No stock transfers match your filters.
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>
</div>
