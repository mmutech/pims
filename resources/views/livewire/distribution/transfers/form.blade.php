<div class="space-y-6">
    <x-page-header :title="$transferId ? 'Edit stock transfer' : 'New stock transfer'" description="Transfer stock between warehouses. Submission routes the document through the approval engine before dispatch.">
        <x-slot name="actions">
            <flux:button variant="ghost" icon="arrow-left" :href="route('distribution.transfers.index')" wire:navigate>All transfers</flux:button>
        </x-slot>
    </x-page-header>

    <x-domain-feedback :message="$feedback" :type="$feedbackType" />

    <form wire:submit="save" class="space-y-6">
        <flux:card class="space-y-4">
            <flux:heading>Transfer details</flux:heading>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <flux:select wire:model.live="form.source_warehouse_id" label="Source warehouse" required>
                    <option value="">Select source</option>
                    @foreach ($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}">{{ $warehouse->code }} — {{ $warehouse->name }}</option>
                    @endforeach
                </flux:select>
                <flux:select wire:model.live="form.destination_warehouse_id" label="Destination warehouse" required>
                    <option value="">Select destination</option>
                    @foreach ($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}">{{ $warehouse->code }} — {{ $warehouse->name }}</option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="form.required_by" type="date" label="Required by" />
            </div>

            <flux:textarea wire:model="form.reason" label="Reason" rows="3" description="Explain why the stock needs to move between warehouses." required />
        </flux:card>

        <flux:card class="space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <flux:heading>Lines</flux:heading>
                    <flux:subheading>Duplicate items are rejected; combine quantities on one line.</flux:subheading>
                </div>
                <flux:button type="button" size="sm" icon="plus" wire:click="addItem">Add line</flux:button>
            </div>

            <div class="space-y-3">
                @foreach ($items as $index => $item)
                    <div class="grid items-end gap-3 rounded-lg border border-zinc-200 p-3 sm:grid-cols-12 dark:border-zinc-700" wire:key="stock-transfer-item-{{ $index }}">
                        <flux:select wire:model.live="items.{{ $index }}.item_id" label="Item" class="sm:col-span-5" required>
                            <option value="">Select item</option>
                            @foreach ($catalogItems as $catalogItem)
                                <option value="{{ $catalogItem->id }}">{{ $catalogItem->code }} — {{ $catalogItem->name }}</option>
                            @endforeach
                        </flux:select>
                        <flux:select wire:model="items.{{ $index }}.unit_of_measure_id" label="Unit" class="sm:col-span-2" required>
                            <option value="">Unit</option>
                            @foreach ($units as $unit)
                                <option value="{{ $unit->id }}">{{ $unit->code }}</option>
                            @endforeach
                        </flux:select>
                        <flux:input wire:model="items.{{ $index }}.requested_quantity" type="number" step="0.0001" min="0" label="Quantity" class="sm:col-span-3" required />
                        <div class="flex justify-end sm:col-span-2">
                            <flux:button type="button" size="sm" variant="ghost" icon="trash" aria-label="Remove line" wire:click="removeItem({{ $index }})" />
                        </div>
                    </div>
                @endforeach
            </div>
        </flux:card>

        <div class="flex justify-end gap-2">
            <flux:button variant="ghost" :href="route('distribution.transfers.index')" wire:navigate>Cancel</flux:button>
            <flux:button type="submit" variant="primary">{{ $transferId ? 'Save changes' : 'Create draft' }}</flux:button>
        </div>
    </form>
</div>
