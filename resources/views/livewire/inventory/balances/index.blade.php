<div class="space-y-6">
    <x-page-header title="Inventory Balances" description="On-hand, reserved and available quantities by warehouse, dimension and batch." />

    <x-domain-feedback :message="$feedback" :type="$feedbackType" />

    <flux:card>
        <div class="flex flex-wrap items-center gap-3">
            <flux:input wire:model.live.debounce.300ms="search" placeholder="Search item code or name" icon="magnifying-glass" class="max-w-sm" />
            <flux:select wire:model.live="warehouseFilter" class="max-w-56">
                <option value="">All warehouses</option>
                @foreach ($warehouses as $warehouse)
                    <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                @endforeach
            </flux:select>
            <flux:checkbox wire:model.live="inStockOnly" label="In stock only" />
            <flux:spacer />
            <span class="text-sm text-zinc-500 dark:text-zinc-400">{{ $balances->total() }} balances</span>
        </div>

        <flux:table :paginate="$balances" class="mt-4">
            <flux:table.columns>
                <flux:table.column>Item</flux:table.column>
                <flux:table.column>Warehouse</flux:table.column>
                <flux:table.column>Dimension</flux:table.column>
                <flux:table.column>Batch</flux:table.column>
                <flux:table.column align="end">On hand</flux:table.column>
                <flux:table.column align="end">Reserved</flux:table.column>
                <flux:table.column align="end">Available</flux:table.column>
                <flux:table.column align="end">Avg cost</flux:table.column>
                <flux:table.column align="end">Value</flux:table.column>
                <flux:table.column>Last movement</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($balances as $balance)
                    <flux:table.row :key="$balance->id">
                        <flux:table.cell>
                            <div class="font-medium">{{ $balance->item?->name }}</div>
                            <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ $balance->item?->code }}</div>
                        </flux:table.cell>
                        <flux:table.cell>{{ $balance->warehouse?->name ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $balance->dimension?->type->label() ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $balance->batch?->batch_number ?? '—' }}</flux:table.cell>
                        <flux:table.cell align="end">{{ rtrim(rtrim($balance->on_hand_quantity, '0'), '.') }}</flux:table.cell>
                        <flux:table.cell align="end">{{ rtrim(rtrim($balance->reserved_quantity, '0'), '.') }}</flux:table.cell>
                        <flux:table.cell align="end">{{ rtrim(rtrim($balance->available_quantity, '0'), '.') }}</flux:table.cell>
                        <flux:table.cell align="end">{{ number_format((float) $balance->average_unit_cost, 4) }}</flux:table.cell>
                        <flux:table.cell align="end">{{ number_format((float) $balance->total_value, 2) }}</flux:table.cell>
                        <flux:table.cell>{{ $balance->last_movement_at?->format('d M Y H:i') ?? '—' }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="10" class="py-10 text-center text-zinc-500 dark:text-zinc-400">
                            No inventory balances match your filters.
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>
</div>
