<div class="space-y-6">
    <x-page-header :title="$adjustmentId ? 'Edit stock adjustment' : 'New stock adjustment'" description="Record a controlled correction to on-hand stock. Increases and decreases are posted as append-only ledger transactions.">
        <x-slot name="actions">
            <flux:button variant="ghost" icon="arrow-left" :href="route('stocktaking.adjustments.index')" wire:navigate>All adjustments</flux:button>
        </x-slot>
    </x-page-header>

    <x-domain-feedback :message="$feedback" :type="$feedbackType" />

    <form wire:submit="save" class="space-y-6">
        <flux:card class="space-y-4">
            <flux:heading>Adjustment details</flux:heading>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <flux:select wire:model.live="form.warehouse_id" label="Warehouse" required>
                    <option value="">Select warehouse</option>
                    @foreach ($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}">{{ $warehouse->code }} — {{ $warehouse->name }}</option>
                    @endforeach
                </flux:select>
                <flux:select wire:model.live="form.adjustment_type" label="Adjustment type" required>
                    @foreach ($types as $type)
                        <option value="{{ $type->value }}">{{ $type->label() }}</option>
                    @endforeach
                </flux:select>
                <flux:select wire:model="form.reason_code" label="Reason code" required>
                    @foreach ($reasons as $reason)
                        <option value="{{ $reason->value }}">{{ $reason->label() }}</option>
                    @endforeach
                </flux:select>
            </div>

            <flux:textarea wire:model="form.reason" label="Reason" rows="3" description="Explain the correction so the variance and audit reports are meaningful." required />
        </flux:card>

        <flux:card class="space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <flux:heading>Lines</flux:heading>
                    <flux:subheading>Quantities are entered as positive values; the adjustment type sets the direction.</flux:subheading>
                </div>
                <flux:button type="button" size="sm" icon="plus" wire:click="addItem">Add line</flux:button>
            </div>

            <div class="space-y-3">
                @foreach ($items as $index => $item)
                    <div class="space-y-3 rounded-lg border border-zinc-200 p-3 dark:border-zinc-700" wire:key="stock-adjustment-item-{{ $index }}">
                        <div class="grid items-end gap-3 sm:grid-cols-12">
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
                            <flux:input wire:model="items.{{ $index }}.quantity" type="number" step="0.0001" min="0" label="Quantity" class="sm:col-span-2" required />
                            <flux:input wire:model="items.{{ $index }}.unit_cost" type="number" step="0.000001" min="0" label="Unit cost" class="sm:col-span-2" />
                            <div class="flex justify-end sm:col-span-1">
                                <flux:button type="button" size="sm" variant="ghost" icon="trash" aria-label="Remove line" wire:click="removeItem({{ $index }})" />
                            </div>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-12">
                            <flux:select wire:model="items.{{ $index }}.batch_id" label="Batch" class="sm:col-span-4" description="Leave blank for non-batch items.">
                                <option value="">No batch</option>
                                @foreach ($batches[$item['item_id'] ?? ''] ?? [] as $batch)
                                    <option value="{{ $batch['id'] }}">{{ $batch['label'] }}</option>
                                @endforeach
                            </flux:select>
                            <flux:input wire:model="items.{{ $index }}.reason" label="Line note" class="sm:col-span-8" placeholder="Optional line-specific explanation" />
                        </div>
                    </div>
                @endforeach
            </div>
        </flux:card>

        <div class="flex justify-end gap-2">
            <flux:button variant="ghost" :href="route('stocktaking.adjustments.index')" wire:navigate>Cancel</flux:button>
            <flux:button type="submit" variant="primary">{{ $adjustmentId ? 'Save changes' : 'Create draft' }}</flux:button>
        </div>
    </form>
</div>
