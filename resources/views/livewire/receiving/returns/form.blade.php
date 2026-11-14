<div class="space-y-6">
    <x-page-header :title="$returnId ? 'Edit supplier return' : 'New supplier return'" description="Return rejected or excess stock to the supplier.">
        <x-slot name="actions">
            <flux:button variant="ghost" icon="arrow-left" :href="route('receiving.returns.index')" wire:navigate>All supplier returns</flux:button>
        </x-slot>
    </x-page-header>

    <x-domain-feedback :message="$feedback" :type="$feedbackType" />

    <form wire:submit="save" class="space-y-6">
        <flux:card class="space-y-4">
            <flux:heading>Return details</flux:heading>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:select wire:model.live="form.grn_id" label="Goods received note">
                    <option value="">Not linked</option>
                    @foreach ($grns as $grn)
                        <option value="{{ $grn->id }}">{{ $grn->document_number }} — {{ $grn->supplier?->name }}</option>
                    @endforeach
                </flux:select>

                <flux:select wire:model="form.supplier_id" label="Supplier" required>
                    <option value="">Select supplier</option>
                    @foreach ($suppliers as $supplier)
                        <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                    @endforeach
                </flux:select>

                <flux:select wire:model="form.warehouse_id" label="Warehouse" required>
                    <option value="">Select warehouse</option>
                    @foreach ($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                    @endforeach
                </flux:select>

                <flux:input wire:model="form.return_date" type="date" label="Return date" />
                <flux:input wire:model="form.credit_note_number" label="Credit note number" />
                <flux:textarea wire:model="form.reason" label="Reason" rows="2" required />
            </div>
        </flux:card>

        <flux:card class="space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <flux:heading>Return lines</flux:heading>
                    <flux:subheading>Posting validates that the stock is available in the selected warehouse.</flux:subheading>
                </div>
                <flux:button type="button" icon="plus" wire:click="addLine">Add line</flux:button>
            </div>

            @foreach ($items as $index => $line)
                <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <flux:select wire:model.live="items.{{ $index }}.item_id" label="Item" required>
                            <option value="">Select item</option>
                            @foreach ($catalogItems as $item)
                                <option value="{{ $item->id }}">{{ $item->code }} — {{ $item->name }}</option>
                            @endforeach
                        </flux:select>

                        <flux:input wire:model="items.{{ $index }}.quantity" type="number" step="0.0001" min="0.0001" label="Quantity" required />
                        <flux:input wire:model="items.{{ $index }}.unit_cost" type="number" step="0.000001" min="0" label="Unit cost" required />

                        <flux:select wire:model="items.{{ $index }}.batch_id" label="Batch">
                            <option value="">Not batched</option>
                            @foreach ($batchesByItem[(int) ($line['item_id'] ?? 0)] ?? [] as $batch)
                                <option value="{{ $batch->id }}">{{ $batch->batch_number }}</option>
                            @endforeach
                        </flux:select>
                    </div>

                    <div class="mt-3 grid gap-3 sm:grid-cols-3">
                        <flux:input wire:model="items.{{ $index }}.serial_numbers" label="Serial numbers" placeholder="Comma separated" />
                        <flux:select wire:model="items.{{ $index }}.condition" label="Condition">
                            @foreach ($conditionOptions as $condition)
                                <option value="{{ $condition->value }}">{{ $condition->label() }}</option>
                            @endforeach
                        </flux:select>
                        <div class="flex items-end justify-end">
                            <flux:button type="button" variant="danger" icon="trash" wire:click="removeLine({{ $index }})">Remove</flux:button>
                        </div>
                    </div>
                </div>
            @endforeach
        </flux:card>

        <div class="flex justify-end gap-2">
            <flux:button variant="ghost" :href="route('receiving.returns.index')" wire:navigate>Cancel</flux:button>
            <flux:button type="submit" variant="primary">{{ $returnId ? 'Save changes' : 'Create draft' }}</flux:button>
        </div>
    </form>
</div>
