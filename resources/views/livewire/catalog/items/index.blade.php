<div class="space-y-6">
    <x-page-header title="Catalog Items" description="Materials, equipment, and consumables available for procurement and issue.">
        <x-slot name="actions">
            @can('create', App\Domain\Catalog\Models\Item::class)
                <flux:button variant="primary" icon="plus" wire:click="openCreate">New item</flux:button>
            @endcan
        </x-slot>
    </x-page-header>

    <x-domain-feedback :message="$feedback" :type="$feedbackType" />

    <flux:card>
        <div class="flex flex-wrap items-center gap-3">
            <flux:input wire:model.live.debounce.300ms="search" placeholder="Search code, name, brand, or MPN" icon="magnifying-glass" class="max-w-sm" />
            <flux:select wire:model.live="categoryFilter" class="max-w-48">
                <option value="">All categories</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="trackingFilter" class="max-w-44">
                <option value="">All tracking</option>
                @foreach ($trackingOptions as $tracking)
                    <option value="{{ $tracking->value }}">{{ $tracking->label() }}</option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="statusFilter" class="max-w-36">
                <option value="">All statuses</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </flux:select>
            <flux:spacer />
            <span class="text-sm text-zinc-500 dark:text-zinc-400">{{ $items->total() }} items</span>
        </div>

        <flux:table :paginate="$items" class="mt-4">
            <flux:table.columns>
                <flux:table.column>Code</flux:table.column>
                <flux:table.column>Item</flux:table.column>
                <flux:table.column>Category</flux:table.column>
                <flux:table.column>Base unit</flux:table.column>
                <flux:table.column>Tracking</flux:table.column>
                <flux:table.column>Standard cost</flux:table.column>
                <flux:table.column>Reorder level</flux:table.column>
                <flux:table.column>Status</flux:table.column>
                <flux:table.column align="end">Actions</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($items as $item)
                    <flux:table.row :key="$item->id">
                        <flux:table.cell variant="strong">{{ $item->code }}</flux:table.cell>
                        <flux:table.cell>
                            <a href="{{ route('catalog.items.show', $item) }}" wire:navigate class="font-medium text-zinc-800 hover:underline dark:text-zinc-100">
                                {{ $item->name }}
                            </a>
                            <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                {{ $item->brand ?? 'No brand' }}@if ($item->model) · {{ $item->model }}@endif
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>{{ $item->category?->name }}</flux:table.cell>
                        <flux:table.cell>{{ $item->baseUnit?->symbol }} <span class="text-xs text-zinc-500 dark:text-zinc-400">({{ $item->baseUnit?->code }})</span></flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" color="zinc">{{ $item->tracking_type->label() }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>{{ number_format((float) $item->standard_cost, 2) }}</flux:table.cell>
                        <flux:table.cell>{{ $item->reorder_level }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$item->is_active ? 'green' : 'zinc'">
                                {{ $item->is_active ? 'Active' : 'Inactive' }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            <div class="flex justify-end gap-1">
                                @can('view', $item)
                                    <flux:button size="sm" variant="ghost" icon="eye" aria-label="Open item" :href="route('catalog.items.show', $item)" wire:navigate />
                                @endcan
                                @can('update', $item)
                                    <flux:button size="sm" variant="ghost" icon="pencil-square" aria-label="Edit item" wire:click="openEdit({{ $item->id }})" />
                                @endcan
                                @can('delete', $item)
                                    <flux:button size="sm" variant="ghost" icon="trash" aria-label="Delete item" wire:click="confirmDelete({{ $item->id }})" />
                                @endcan
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="9" class="py-10 text-center text-zinc-500 dark:text-zinc-400">
                            No items match your filters.
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <flux:modal wire:model="showForm" class="md:w-[48rem]">
        <form wire:submit="save" class="space-y-4">
            <div>
                <flux:heading size="lg">{{ $editingId ? 'Edit item' : 'New item' }}</flux:heading>
                <flux:subheading>Item codes are immutable. Specifications and alternate units are managed from the item page.</flux:subheading>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:select wire:model="form.category_id" label="Category" required>
                    <option value="">Select a category</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->code }} · {{ $category->name }}</option>
                    @endforeach
                </flux:select>

                <flux:select wire:model="form.base_unit_of_measure_id" label="Base unit of measure" required>
                    <option value="">Select a unit</option>
                    @foreach ($units as $unit)
                        <option value="{{ $unit->id }}">{{ $unit->name }} ({{ $unit->symbol }})</option>
                    @endforeach
                </flux:select>

                <flux:input wire:model="form.code" label="Code" placeholder="e.g. ITM-0001" :disabled="$editingId !== null" required />
                <flux:input wire:model="form.name" label="Name" placeholder="e.g. 11kV Drop-out Fuse" required />

                <flux:input wire:model="form.brand" label="Brand" />
                <flux:input wire:model="form.model" label="Model" />
                <flux:input wire:model="form.manufacturer_part_number" label="Manufacturer part number" />

                <flux:select wire:model="form.tracking_type" label="Tracking type">
                    @foreach ($trackingOptions as $tracking)
                        <option value="{{ $tracking->value }}">{{ $tracking->label() }}</option>
                    @endforeach
                </flux:select>

                <flux:select wire:model="form.valuation_method" label="Valuation method">
                    @foreach ($valuationOptions as $valuation)
                        <option value="{{ $valuation->value }}">{{ $valuation->label() }}</option>
                    @endforeach
                </flux:select>

                <flux:input wire:model="form.minimum_stock" label="Minimum stock" />
                <flux:input wire:model="form.maximum_stock" label="Maximum stock" />
                <flux:input wire:model="form.reorder_level" label="Reorder level" />
                <flux:input wire:model="form.reorder_quantity" label="Reorder quantity" />
                <flux:input wire:model="form.standard_cost" label="Standard cost" />
                <flux:input wire:model="form.shelf_life_days" type="number" min="0" label="Shelf life (days)" />
            </div>

            <flux:textarea wire:model="form.description" label="Description" rows="2" />
            <flux:switch wire:model="form.requires_quality_inspection" label="Requires quality inspection" />
            <flux:switch wire:model="form.is_active" label="Active" description="Inactive items cannot be ordered or issued." />

            <div class="flex justify-end gap-2 pt-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showForm', false)">Cancel</flux:button>
                <flux:button type="submit" variant="primary">{{ $editingId ? 'Save changes' : 'Create item' }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model="showDeleteModal" class="md:w-96">
        <div class="space-y-4">
            <div>
                <flux:heading size="lg">Delete item</flux:heading>
                <flux:subheading>Items with batches or serial numbers cannot be deleted.</flux:subheading>
            </div>
            <p class="text-sm text-zinc-600 dark:text-zinc-300">
                Delete <span class="font-semibold">{{ $deletingName }}</span>?
            </p>
            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showDeleteModal', false)">Cancel</flux:button>
                <flux:button type="button" variant="danger" wire:click="delete">Delete item</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
