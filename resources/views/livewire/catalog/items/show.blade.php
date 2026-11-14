<div class="space-y-6">
    <x-page-header :title="$item->name" :description="'Item '.$item->code.' · '.($item->category?->name ?? 'No category')">
        <x-slot name="actions">
            <flux:button variant="ghost" icon="arrow-left" :href="route('catalog.items.index')" wire:navigate>All items</flux:button>
        </x-slot>
    </x-page-header>

    <x-domain-feedback :message="$feedback" :type="$feedbackType" />

    <div class="grid gap-6 lg:grid-cols-3">
        <flux:card class="min-w-0 lg:col-span-2">
            <div class="flex items-center justify-between">
                <flux:heading>Item details</flux:heading>
                <div class="flex items-center gap-2">
                    <flux:badge size="sm" color="zinc">{{ $item->tracking_type->label() }}</flux:badge>
                    <flux:badge size="sm" :color="$item->is_active ? 'green' : 'zinc'">
                        {{ $item->is_active ? 'Active' : 'Inactive' }}
                    </flux:badge>
                </div>
            </div>

            <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Category</dt>
                    <dd class="mt-1 text-sm text-zinc-800 dark:text-zinc-100">{{ $item->category?->name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Base unit</dt>
                    <dd class="mt-1 text-sm text-zinc-800 dark:text-zinc-100">{{ $item->baseUnit?->name }} ({{ $item->baseUnit?->symbol }})</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Valuation method</dt>
                    <dd class="mt-1 text-sm text-zinc-800 dark:text-zinc-100">{{ $item->valuation_method->label() }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Standard cost</dt>
                    <dd class="mt-1 text-sm text-zinc-800 dark:text-zinc-100">{{ number_format((float) $item->standard_cost, 2) }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Brand / Model</dt>
                    <dd class="mt-1 text-sm text-zinc-800 dark:text-zinc-100">{{ collect([$item->brand, $item->model])->filter()->implode(' / ') ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Manufacturer part number</dt>
                    <dd class="mt-1 text-sm text-zinc-800 dark:text-zinc-100">{{ $item->manufacturer_part_number ?? '—' }}</dd>
                </div>
            </dl>

            @if ($item->description)
                <div class="mt-4 border-t border-zinc-100 pt-4 dark:border-zinc-700">
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Description</dt>
                    <dd class="mt-1 text-sm text-zinc-800 dark:text-zinc-100">{{ $item->description }}</dd>
                </div>
            @endif
        </flux:card>

        <flux:card class="min-w-0">
            <flux:heading>Stock parameters</flux:heading>
            <div class="mt-4 space-y-3 text-sm">
                <div class="flex items-center justify-between">
                    <span class="text-zinc-500 dark:text-zinc-400">Minimum stock</span>
                    <span class="font-medium text-zinc-800 dark:text-zinc-100">{{ $item->minimum_stock }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-zinc-500 dark:text-zinc-400">Maximum stock</span>
                    <span class="font-medium text-zinc-800 dark:text-zinc-100">{{ $item->maximum_stock ?? '—' }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-zinc-500 dark:text-zinc-400">Reorder level</span>
                    <span class="font-medium text-zinc-800 dark:text-zinc-100">{{ $item->reorder_level }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-zinc-500 dark:text-zinc-400">Reorder quantity</span>
                    <span class="font-medium text-zinc-800 dark:text-zinc-100">{{ $item->reorder_quantity }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-zinc-500 dark:text-zinc-400">Quality inspection</span>
                    <span class="font-medium text-zinc-800 dark:text-zinc-100">{{ $item->requires_quality_inspection ? 'Required' : 'Not required' }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-zinc-500 dark:text-zinc-400">Shelf life</span>
                    <span class="font-medium text-zinc-800 dark:text-zinc-100">{{ $item->shelf_life_days !== null ? $item->shelf_life_days.' days' : '—' }}</span>
                </div>
            </div>
        </flux:card>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <flux:card class="min-w-0">
            <flux:heading>Specifications</flux:heading>
            <flux:subheading>Technical attributes captured for procurement and inspection.</flux:subheading>

            @can('update', $item)
                <form wire:submit="addSpecification" class="mt-4 flex flex-wrap items-end gap-2">
                    <flux:input wire:model="specForm.attribute" label="Attribute" placeholder="e.g. Voltage" class="min-w-32 flex-1" />
                    <flux:input wire:model="specForm.value" label="Value" placeholder="e.g. 11kV" class="min-w-32 flex-1" />
                    <flux:select wire:model="specForm.unit_of_measure_id" label="Unit" class="w-32">
                        <option value="">None</option>
                        @foreach ($units as $unit)
                            <option value="{{ $unit->id }}">{{ $unit->symbol }}</option>
                        @endforeach
                    </flux:select>
                    <flux:button type="submit" variant="primary">Add</flux:button>
                </form>
            @endcan

            <div class="mt-4 divide-y divide-zinc-100 dark:divide-zinc-700">
                @forelse ($specifications as $specification)
                    <div class="flex items-center justify-between gap-3 py-2">
                        <div>
                            <div class="text-sm font-medium text-zinc-800 dark:text-zinc-100">{{ $specification->attribute }}</div>
                            <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                {{ $specification->value }} @if ($specification->unitOfMeasure) {{ $specification->unitOfMeasure->symbol }} @endif
                            </div>
                        </div>
                        @can('update', $item)
                            <flux:button size="sm" variant="ghost" icon="x-mark" aria-label="Remove specification" wire:click="deleteSpecification({{ $specification->id }})" wire:confirm="Remove this specification?" />
                        @endcan
                    </div>
                @empty
                    <p class="py-4 text-sm text-zinc-500 dark:text-zinc-400">No specifications recorded.</p>
                @endforelse
            </div>
        </flux:card>

        <flux:card class="min-w-0">
            <flux:heading>Units & conversions</flux:heading>
            <flux:subheading>Alternate units convert to the base unit for stock posting.</flux:subheading>

            @can('update', $item)
                <form wire:submit="addUnit" class="mt-4 flex flex-wrap items-end gap-2">
                    <flux:select wire:model="unitForm.unit_of_measure_id" label="Unit" class="min-w-32 flex-1">
                        <option value="">Select a unit</option>
                        @foreach ($units as $unit)
                            <option value="{{ $unit->id }}">{{ $unit->name }} ({{ $unit->symbol }})</option>
                        @endforeach
                    </flux:select>
                    <flux:input wire:model="unitForm.conversion_factor" label="Conversion factor" class="w-40" />
                    <flux:button type="submit" variant="primary">Add</flux:button>
                </form>
            @endcan

            <flux:table class="mt-4">
                <flux:table.columns>
                    <flux:table.column>Unit</flux:table.column>
                    <flux:table.column>Factor</flux:table.column>
                    <flux:table.column>Type</flux:table.column>
                    <flux:table.column align="end">Actions</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($itemUnits as $itemUnit)
                        <flux:table.row :key="$itemUnit->id">
                            <flux:table.cell variant="strong">{{ $itemUnit->unitOfMeasure?->name }} ({{ $itemUnit->unitOfMeasure?->symbol }})</flux:table.cell>
                            <flux:table.cell>{{ $itemUnit->conversion_factor }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm" :color="$itemUnit->is_base ? 'blue' : 'zinc'">
                                    {{ $itemUnit->is_base ? 'Base' : 'Alternate' }}
                                </flux:badge>
                            </flux:table.cell>
                            <flux:table.cell align="end">
                                @if (! $itemUnit->is_base)
                                    @can('update', $item)
                                        <flux:button size="sm" variant="ghost" icon="x-mark" aria-label="Remove unit" wire:click="deleteUnit({{ $itemUnit->id }})" wire:confirm="Remove this alternate unit?" />
                                    @endcan
                                @endif
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </flux:card>
    </div>

    @if ($item->tracksBatches())
        <flux:card>
            <flux:heading>Batches</flux:heading>
            <flux:subheading>Batch numbers recorded against this item.</flux:subheading>

            @can('update', $item)
                <form wire:submit="addBatch" class="mt-4 flex flex-wrap items-end gap-2">
                    <flux:input wire:model="batchForm.batch_number" label="Batch number" class="min-w-40 flex-1" />
                    <flux:input wire:model="batchForm.manufacture_date" type="date" label="Manufactured" class="w-40" />
                    <flux:input wire:model="batchForm.expiry_date" type="date" label="Expires" class="w-40" />
                    <flux:select wire:model="batchForm.supplier_id" label="Supplier" class="min-w-40 flex-1">
                        <option value="">None</option>
                        @foreach ($suppliers as $supplier)
                            <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                        @endforeach
                    </flux:select>
                    <flux:button type="submit" variant="primary">Add batch</flux:button>
                </form>
            @endcan

            <flux:table class="mt-4">
                <flux:table.columns>
                    <flux:table.column>Batch</flux:table.column>
                    <flux:table.column>Manufactured</flux:table.column>
                    <flux:table.column>Expires</flux:table.column>
                    <flux:table.column>Supplier</flux:table.column>
                    <flux:table.column>Status</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($batches as $batch)
                        <flux:table.row :key="$batch->id">
                            <flux:table.cell variant="strong">{{ $batch->batch_number }}</flux:table.cell>
                            <flux:table.cell>{{ $batch->manufacture_date?->format('d M Y') ?? '—' }}</flux:table.cell>
                            <flux:table.cell>{{ $batch->expiry_date?->format('d M Y') ?? '—' }}</flux:table.cell>
                            <flux:table.cell>{{ $batch->supplier?->name ?? '—' }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm" :color="$batch->status->isAvailable() ? 'green' : 'zinc'">{{ $batch->status->label() }}</flux:badge>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="5" class="py-6 text-center text-zinc-500 dark:text-zinc-400">No batches recorded.</flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </flux:card>
    @endif

    @if ($item->tracksSerials())
        <flux:card>
            <flux:heading>Serial numbers</flux:heading>
            <flux:subheading>Serialised units tracked through receipt, issue, and return.</flux:subheading>

            @can('update', $item)
                <form wire:submit="addSerial" class="mt-4 flex flex-wrap items-end gap-2">
                    <flux:input wire:model="serialForm.serial_number" label="Serial number" class="min-w-48 flex-1" />
                    <flux:select wire:model="serialForm.batch_id" label="Batch" class="min-w-40 flex-1">
                        <option value="">No batch</option>
                        @foreach ($batches as $batch)
                            <option value="{{ $batch->id }}">{{ $batch->batch_number }}</option>
                        @endforeach
                    </flux:select>
                    <flux:button type="submit" variant="primary">Add serial</flux:button>
                </form>
            @endcan

            <flux:table class="mt-4">
                <flux:table.columns>
                    <flux:table.column>Serial</flux:table.column>
                    <flux:table.column>Batch</flux:table.column>
                    <flux:table.column>Warehouse</flux:table.column>
                    <flux:table.column>Status</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($serials as $serial)
                        <flux:table.row :key="$serial->id">
                            <flux:table.cell variant="strong">{{ $serial->serial_number }}</flux:table.cell>
                            <flux:table.cell>{{ $serial->batch?->batch_number ?? '—' }}</flux:table.cell>
                            <flux:table.cell>{{ $serial->currentWarehouse?->name ?? '—' }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm" color="zinc">{{ $serial->status->label() }}</flux:badge>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="4" class="py-6 text-center text-zinc-500 dark:text-zinc-400">No serial numbers recorded.</flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </flux:card>
    @endif

    @if ($activity->isNotEmpty())
        <flux:card>
            <flux:heading>Item activity</flux:heading>
            <div class="mt-4 space-y-2">
                @foreach ($activity as $entry)
                    <div class="flex items-center justify-between gap-3 border-b border-zinc-100 pb-2 text-sm last:border-0 last:pb-0 dark:border-zinc-700">
                        <div>
                            <span class="font-medium text-zinc-800 dark:text-zinc-100">{{ $entry->description }}</span>
                            <span class="text-zinc-500 dark:text-zinc-400">by {{ $entry->causer?->name ?? 'System' }}</span>
                        </div>
                        <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ $entry->created_at?->diffForHumans() }}</span>
                    </div>
                @endforeach
            </div>
        </flux:card>
    @endif
</div>
