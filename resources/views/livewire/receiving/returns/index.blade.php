<div class="space-y-6">
    <x-page-header title="Supplier Returns" description="Return rejected or excess goods to suppliers with a posted stock movement.">
        <x-slot name="actions">
            @can('create', App\Domain\Receiving\Models\SupplierReturn::class)
                <flux:button variant="primary" icon="plus" :href="route('receiving.returns.create')" wire:navigate>New supplier return</flux:button>
            @endcan
        </x-slot>
    </x-page-header>

    <x-domain-feedback :message="$feedback" :type="$feedbackType" />

    <flux:card>
        <div class="flex flex-wrap items-center gap-3">
            <flux:input wire:model.live.debounce.300ms="search" placeholder="Search number, supplier or credit note" icon="magnifying-glass" class="max-w-sm" />
            <flux:select wire:model.live="statusFilter" class="max-w-48">
                <option value="">All statuses</option>
                @foreach ($statusOptions as $status)
                    <option value="{{ $status->value }}">{{ $status->label() }}</option>
                @endforeach
            </flux:select>
            <flux:spacer />
            <span class="text-sm text-zinc-500 dark:text-zinc-400">{{ $returns->total() }} returns</span>
        </div>

        <flux:table :paginate="$returns" class="mt-4">
            <flux:table.columns>
                <flux:table.column>Number</flux:table.column>
                <flux:table.column>Supplier</flux:table.column>
                <flux:table.column>Warehouse</flux:table.column>
                <flux:table.column>GRN</flux:table.column>
                <flux:table.column align="end">Lines</flux:table.column>
                <flux:table.column>Return date</flux:table.column>
                <flux:table.column>Status</flux:table.column>
                <flux:table.column align="end">Actions</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($returns as $return)
                    <flux:table.row :key="$return->id">
                        <flux:table.cell variant="strong">
                            <a href="{{ route('receiving.returns.show', $return) }}" wire:navigate class="hover:underline">{{ $return->document_number }}</a>
                        </flux:table.cell>
                        <flux:table.cell>{{ $return->supplier?->name ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $return->warehouse?->name ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $return->grn?->document_number ?? '—' }}</flux:table.cell>
                        <flux:table.cell align="end">{{ $return->items_count }}</flux:table.cell>
                        <flux:table.cell>{{ $return->return_date?->format('d M Y') ?? '—' }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="match ($return->status) {
                                App\Enums\ReturnStatus::Draft => 'zinc',
                                App\Enums\ReturnStatus::PendingInspection => 'blue',
                                App\Enums\ReturnStatus::Assessed => 'amber',
                                App\Enums\ReturnStatus::Posted => 'green',
                                App\Enums\ReturnStatus::Rejected => 'red',
                                App\Enums\ReturnStatus::Cancelled => 'red',
                            }">{{ $return->status->label() }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            <div class="flex justify-end gap-1">
                                <flux:button size="sm" variant="ghost" icon="eye" aria-label="Open supplier return" :href="route('receiving.returns.show', $return)" wire:navigate />
                                @can('update', $return)
                                    <flux:button size="sm" variant="ghost" icon="pencil-square" aria-label="Edit supplier return" :href="route('receiving.returns.edit', $return)" wire:navigate />
                                @endcan
                                @can('delete', $return)
                                    <flux:button size="sm" variant="ghost" icon="trash" aria-label="Delete supplier return" wire:click="delete({{ $return->id }})" wire:confirm="Delete this draft supplier return?" />
                                @endcan
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="8" class="py-10 text-center text-zinc-500 dark:text-zinc-400">
                            No supplier returns match your filters.
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>
</div>
