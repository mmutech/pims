<div class="space-y-6">
    <x-page-header title="Units of Measure" description="Canonical units used for quantities, weights, lengths, and conversions.">
        <x-slot name="actions">
            @can('create', App\Domain\Catalog\Models\UnitOfMeasure::class)
                <flux:button variant="primary" icon="plus" wire:click="openCreate">New unit</flux:button>
            @endcan
        </x-slot>
    </x-page-header>

    <x-domain-feedback :message="$feedback" :type="$feedbackType" />

    <flux:card>
        <div class="flex flex-wrap items-center gap-3">
            <flux:input wire:model.live.debounce.300ms="search" placeholder="Search units" icon="magnifying-glass" class="max-w-sm" />
            <flux:select wire:model.live="typeFilter" class="max-w-48">
                <option value="">All types</option>
                @foreach ($typeOptions as $type)
                    <option value="{{ $type->value }}">{{ $type->label() }}</option>
                @endforeach
            </flux:select>
            <flux:spacer />
            <span class="text-sm text-zinc-500 dark:text-zinc-400">{{ $units->total() }} units</span>
        </div>

        <flux:table :paginate="$units" class="mt-4">
            <flux:table.columns>
                <flux:table.column>Code</flux:table.column>
                <flux:table.column>Name</flux:table.column>
                <flux:table.column>Symbol</flux:table.column>
                <flux:table.column>Type</flux:table.column>
                <flux:table.column>Decimals</flux:table.column>
                <flux:table.column>Items</flux:table.column>
                <flux:table.column>Status</flux:table.column>
                <flux:table.column align="end">Actions</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($units as $unit)
                    <flux:table.row :key="$unit->id">
                        <flux:table.cell variant="strong">{{ $unit->code }}</flux:table.cell>
                        <flux:table.cell>{{ $unit->name }}</flux:table.cell>
                        <flux:table.cell>{{ $unit->symbol }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" color="zinc">{{ $unit->type->label() }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>{{ $unit->decimal_places }}</flux:table.cell>
                        <flux:table.cell>{{ $unit->items_count }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$unit->is_active ? 'green' : 'zinc'">
                                {{ $unit->is_active ? 'Active' : 'Inactive' }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            <div class="flex justify-end gap-1">
                                @can('update', $unit)
                                    <flux:button size="sm" variant="ghost" icon="pencil-square" aria-label="Edit unit" wire:click="openEdit({{ $unit->id }})" />
                                @endcan
                                @can('delete', $unit)
                                    <flux:button size="sm" variant="ghost" icon="trash" aria-label="Delete unit" wire:click="confirmDelete({{ $unit->id }})" />
                                @endcan
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="8" class="py-10 text-center text-zinc-500 dark:text-zinc-400">
                            No units match your search.
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <flux:modal wire:model="showForm" class="md:w-[32rem]">
        <form wire:submit="save" class="space-y-4">
            <div>
                <flux:heading size="lg">{{ $editingId ? 'Edit unit' : 'New unit' }}</flux:heading>
                <flux:subheading>Unit codes are immutable. Decimal places control quantity precision.</flux:subheading>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="form.code" label="Code" placeholder="e.g. M" :disabled="$editingId !== null" required />
                <flux:input wire:model="form.symbol" label="Symbol" placeholder="e.g. m" required />
            </div>

            <flux:input wire:model="form.name" label="Name" placeholder="e.g. Metre" required />

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:select wire:model="form.type" label="Type">
                    @foreach ($typeOptions as $type)
                        <option value="{{ $type->value }}">{{ $type->label() }}</option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="form.decimal_places" type="number" min="0" max="6" label="Decimal places" required />
            </div>

            <flux:switch wire:model="form.is_active" label="Active" />

            <div class="flex justify-end gap-2 pt-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showForm', false)">Cancel</flux:button>
                <flux:button type="submit" variant="primary">{{ $editingId ? 'Save changes' : 'Create unit' }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model="showDeleteModal" class="md:w-96">
        <div class="space-y-4">
            <div>
                <flux:heading size="lg">Delete unit</flux:heading>
                <flux:subheading>Units referenced by items cannot be deleted.</flux:subheading>
            </div>
            <p class="text-sm text-zinc-600 dark:text-zinc-300">
                Delete <span class="font-semibold">{{ $deletingName }}</span>?
            </p>
            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showDeleteModal', false)">Cancel</flux:button>
                <flux:button type="button" variant="danger" wire:click="delete">Delete unit</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
