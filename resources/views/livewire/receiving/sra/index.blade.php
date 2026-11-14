<div class="space-y-6">
    <x-page-header title="Store Receipt Advices" description="Record supplier deliveries, verify quantities and route goods through inspection.">
        <x-slot name="actions">
            @can('create', App\Domain\Receiving\Models\StoreReceiptAdvice::class)
                <flux:button variant="primary" icon="plus" :href="route('receiving.sra.create')" wire:navigate>New receipt advice</flux:button>
            @endcan
        </x-slot>
    </x-page-header>

    <x-domain-feedback :message="$feedback" :type="$feedbackType" />

    <flux:card>
        <div class="flex flex-wrap items-center gap-3">
            <flux:input wire:model.live.debounce.300ms="search" placeholder="Search number, supplier or PO" icon="magnifying-glass" class="max-w-sm" />
            <flux:select wire:model.live="statusFilter" class="max-w-48">
                <option value="">All statuses</option>
                @foreach ($statusOptions as $status)
                    <option value="{{ $status->value }}">{{ $status->label() }}</option>
                @endforeach
            </flux:select>
            <flux:spacer />
            <span class="text-sm text-zinc-500 dark:text-zinc-400">{{ $advices->total() }} advices</span>
        </div>

        <flux:table :paginate="$advices" class="mt-4">
            <flux:table.columns>
                <flux:table.column>Number</flux:table.column>
                <flux:table.column>PO</flux:table.column>
                <flux:table.column>Supplier</flux:table.column>
                <flux:table.column>Warehouse</flux:table.column>
                <flux:table.column align="end">Lines</flux:table.column>
                <flux:table.column>Status</flux:table.column>
                <flux:table.column align="end">Actions</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($advices as $sra)
                    <flux:table.row :key="$sra->id">
                        <flux:table.cell variant="strong">
                            <a href="{{ route('receiving.sra.show', $sra) }}" wire:navigate class="hover:underline">{{ $sra->document_number }}</a>
                        </flux:table.cell>
                        <flux:table.cell>{{ $sra->purchaseOrder?->document_number ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $sra->supplier?->name ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $sra->warehouse?->name ?? '—' }}</flux:table.cell>
                        <flux:table.cell align="end">{{ $sra->items_count }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="match ($sra->status) {
                                App\Enums\ReceiptStatus::Draft => 'zinc',
                                App\Enums\ReceiptStatus::PendingVerification => 'blue',
                                App\Enums\ReceiptStatus::Verified => 'green',
                                App\Enums\ReceiptStatus::PendingInspection => 'amber',
                                App\Enums\ReceiptStatus::Inspected => 'lime',
                                App\Enums\ReceiptStatus::Posted => 'teal',
                                App\Enums\ReceiptStatus::Reversed => 'purple',
                                App\Enums\ReceiptStatus::Cancelled => 'red',
                            }">{{ $sra->status->label() }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            <div class="flex justify-end gap-1">
                                <flux:button size="sm" variant="ghost" icon="eye" aria-label="Open receipt advice" :href="route('receiving.sra.show', $sra)" wire:navigate />
                                @can('update', $sra)
                                    <flux:button size="sm" variant="ghost" icon="pencil-square" aria-label="Edit receipt advice" :href="route('receiving.sra.edit', $sra)" wire:navigate />
                                @endcan
                                @can('delete', $sra)
                                    <flux:button size="sm" variant="ghost" icon="trash" aria-label="Delete receipt advice" wire:click="delete({{ $sra->id }})" wire:confirm="Delete this draft receipt advice?" />
                                @endcan
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7" class="py-10 text-center text-zinc-500 dark:text-zinc-400">
                            No receipt advices match your filters.
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>
</div>
