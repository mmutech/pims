<div class="space-y-6">
    <x-page-header :title="$sraId ? 'Edit receipt advice' : 'New receipt advice'" description="Capture what the supplier delivered against an approved purchase order.">
        <x-slot name="actions">
            <flux:button variant="ghost" icon="arrow-left" :href="route('receiving.sra.index')" wire:navigate>All receipt advices</flux:button>
        </x-slot>
    </x-page-header>

    <x-domain-feedback :message="$feedback" :type="$feedbackType" />

    <form wire:submit="save" class="space-y-6">
        <flux:card class="space-y-4">
            <flux:heading>Delivery details</flux:heading>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:select wire:model.live="form.purchase_order_id" label="Purchase order" required>
                    <option value="">Select purchase order</option>
                    @foreach ($orders as $order)
                        <option value="{{ $order->id }}">{{ $order->document_number }} — {{ $order->supplier?->name }}</option>
                    @endforeach
                </flux:select>

                <flux:input wire:model="form.expected_delivery_date" type="date" label="Expected delivery date" />
                <flux:input wire:model="form.vehicle_number" label="Vehicle number" placeholder="KJA-123-AB" />
                <flux:input wire:model="form.driver_name" label="Driver name" />
                <flux:input wire:model="form.driver_phone" label="Driver phone" />
                <flux:input wire:model="form.delivery_note_number" label="Delivery note number" />
            </div>
        </flux:card>

        <flux:card class="space-y-4">
            <div>
                <flux:heading>Delivery lines</flux:heading>
                <flux:subheading>Delivered quantities cannot exceed the outstanding quantity on the purchase order.</flux:subheading>
            </div>

            @forelse ($items as $index => $item)
                <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div>
                            <div class="text-sm font-semibold">{{ $item['label'] }}</div>
                            <div class="text-xs text-zinc-500 dark:text-zinc-400">Unit: {{ $item['unit'] ?? '—' }}</div>
                        </div>
                        <flux:badge size="sm" variant="outline">{{ rtrim(rtrim($item['expected_quantity'], '0'), '.') }} outstanding</flux:badge>
                    </div>

                    <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <flux:input wire:model="items.{{ $index }}.expected_quantity" type="number" step="0.0001" min="0" label="Expected" required />
                        <flux:input wire:model="items.{{ $index }}.delivered_quantity" type="number" step="0.0001" min="0" label="Delivered" required />
                        <flux:input wire:model="items.{{ $index }}.batch_number" label="Batch number" />
                        <flux:input wire:model="items.{{ $index }}.serial_numbers" label="Serial numbers" placeholder="Comma separated" />
                    </div>

                    <flux:input wire:model="items.{{ $index }}.condition_notes" label="Condition notes" class="mt-3" />
                </div>
            @empty
                <flux:callout icon="information-circle">Select a purchase order to load its outstanding lines.</flux:callout>
            @endforelse
        </flux:card>

        <div class="flex justify-end gap-2">
            <flux:button variant="ghost" :href="route('receiving.sra.index')" wire:navigate>Cancel</flux:button>
            <flux:button type="submit" variant="primary">Save receipt advice</flux:button>
        </div>
    </form>
</div>
