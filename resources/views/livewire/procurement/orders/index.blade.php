<div class="space-y-6">
    <x-page-header title="Purchase Orders" description="Approved commitments to suppliers for the delivery of goods and services.">
        <x-slot name="actions">
            @can('create', App\Domain\Procurement\Models\PurchaseOrder::class)
                <flux:button variant="primary" icon="plus" :href="route('procurement.orders.create')" wire:navigate>New purchase order</flux:button>
            @endcan
        </x-slot>
    </x-page-header>

    <x-domain-feedback :message="$feedback" :type="$feedbackType" />

    <flux:card>
        <div class="flex flex-wrap items-center gap-3">
            <flux:input wire:model.live.debounce.300ms="search" placeholder="Search number or supplier" icon="magnifying-glass" class="max-w-sm" />
            <flux:select wire:model.live="statusFilter" class="max-w-48">
                <option value="">All statuses</option>
                @foreach ($statusOptions as $status)
                    <option value="{{ $status->value }}">{{ $status->label() }}</option>
                @endforeach
            </flux:select>
            <flux:spacer />
            <span class="text-sm text-zinc-500 dark:text-zinc-400">{{ $orders->total() }} orders</span>
        </div>

        <flux:table :paginate="$orders" class="mt-4">
            <flux:table.columns>
                <flux:table.column>Number</flux:table.column>
                <flux:table.column>Supplier</flux:table.column>
                <flux:table.column>Delivery warehouse</flux:table.column>
                <flux:table.column align="end">Version</flux:table.column>
                <flux:table.column align="end">Total</flux:table.column>
                <flux:table.column>Status</flux:table.column>
                <flux:table.column align="end">Actions</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($orders as $order)
                    <flux:table.row :key="$order->id">
                        <flux:table.cell variant="strong">
                            <a href="{{ route('procurement.orders.show', $order) }}" wire:navigate class="hover:underline">{{ $order->document_number }}</a>
                        </flux:table.cell>
                        <flux:table.cell>{{ $order->supplier?->name ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $order->deliveryWarehouse?->name ?? '—' }}</flux:table.cell>
                        <flux:table.cell align="end">v{{ $order->version }}</flux:table.cell>
                        <flux:table.cell align="end">{{ $order->currency }} {{ number_format((float) $order->total_amount, 2) }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="match ($order->status) {
                                App\Enums\PurchaseOrderStatus::Draft => 'zinc',
                                App\Enums\PurchaseOrderStatus::PendingApproval => 'blue',
                                App\Enums\PurchaseOrderStatus::Approved => 'green',
                                App\Enums\PurchaseOrderStatus::PartiallyReceived => 'lime',
                                App\Enums\PurchaseOrderStatus::FullyReceived => 'teal',
                                App\Enums\PurchaseOrderStatus::Closed => 'purple',
                                App\Enums\PurchaseOrderStatus::Cancelled => 'red',
                            }">{{ $order->status->label() }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            <div class="flex justify-end gap-1">
                                @can('view', $order)
                                    <flux:button size="sm" variant="ghost" icon="eye" aria-label="Open purchase order" :href="route('procurement.orders.show', $order)" wire:navigate />
                                @endcan
                                @can('update', $order)
                                    <flux:button size="sm" variant="ghost" icon="pencil-square" aria-label="Edit purchase order" :href="route('procurement.orders.edit', $order)" wire:navigate />
                                @endcan
                                @can('delete', $order)
                                    <flux:button size="sm" variant="ghost" icon="trash" aria-label="Delete purchase order" wire:click="delete({{ $order->id }})" wire:confirm="Delete this draft purchase order?" />
                                @endcan
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="8" class="py-10 text-center text-zinc-500 dark:text-zinc-400">
                            No purchase orders match your filters.
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>
</div>
