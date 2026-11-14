<div class="space-y-6">
    <x-page-header title="Business Units" description="Operating units that own departments, projects, and warehouses.">
        <x-slot name="actions">
            @can('create', App\Domain\Organization\Models\BusinessUnit::class)
                <flux:button variant="primary" icon="plus" wire:click="openCreate">New business unit</flux:button>
            @endcan
        </x-slot>
    </x-page-header>

    <x-domain-feedback :message="$feedback" :type="$feedbackType" />

    <flux:card>
        <div class="flex flex-wrap items-center gap-3">
            <flux:input wire:model.live.debounce.300ms="search" placeholder="Search by code or name" icon="magnifying-glass" class="max-w-sm" />
            <flux:spacer />
            <span class="text-sm text-zinc-500 dark:text-zinc-400">{{ $businessUnits->total() }} business units</span>
        </div>

        <flux:table :paginate="$businessUnits" class="mt-4">
            <flux:table.columns>
                <flux:table.column>Code</flux:table.column>
                <flux:table.column>Name</flux:table.column>
                <flux:table.column>Warehouses</flux:table.column>
                <flux:table.column>Status</flux:table.column>
                <flux:table.column align="end">Actions</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($businessUnits as $businessUnit)
                    <flux:table.row :key="$businessUnit->id">
                        <flux:table.cell variant="strong">{{ $businessUnit->code }}</flux:table.cell>
                        <flux:table.cell>
                            <div class="font-medium text-zinc-800 dark:text-zinc-100">{{ $businessUnit->name }}</div>
                            @if ($businessUnit->description)
                                <div class="max-w-xs truncate text-xs text-zinc-500 dark:text-zinc-400">{{ $businessUnit->description }}</div>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>{{ $businessUnit->warehouses_count }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$businessUnit->is_active ? 'green' : 'zinc'">
                                {{ $businessUnit->is_active ? 'Active' : 'Inactive' }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            <div class="flex justify-end gap-1">
                                @can('update', $businessUnit)
                                    <flux:button size="sm" variant="ghost" icon="pencil-square" aria-label="Edit business unit" wire:click="openEdit({{ $businessUnit->id }})" />
                                @endcan
                                @can('delete', $businessUnit)
                                    <flux:button size="sm" variant="ghost" icon="trash" aria-label="Delete business unit" wire:click="confirmDelete({{ $businessUnit->id }})" />
                                @endcan
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6" class="py-10 text-center text-zinc-500 dark:text-zinc-400">
                            No business units match your search.
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <flux:modal wire:model="showForm" class="md:w-[32rem]">
        <form wire:submit="save" class="space-y-4">
            <div>
                <flux:heading size="lg">{{ $editingId ? 'Edit business unit' : 'New business unit' }}</flux:heading>
                <flux:subheading>Codes are immutable.</flux:subheading>
            </div>

            <flux:input wire:model="form.code" label="Code" placeholder="e.g. BU-IKJ" :disabled="$editingId !== null" required />
            <flux:input wire:model="form.name" label="Name" placeholder="e.g. Ikeja Business Unit" required />
            <flux:textarea wire:model="form.description" label="Description" rows="3" />
            <flux:switch wire:model="form.is_active" label="Active" description="Inactive units cannot receive new departments or projects." />

            <div class="flex justify-end gap-2 pt-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showForm', false)">Cancel</flux:button>
                <flux:button type="submit" variant="primary">{{ $editingId ? 'Save changes' : 'Create business unit' }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model="showDeleteModal" class="md:w-96">
        <div class="space-y-4">
            <div>
                <flux:heading size="lg">Delete business unit</flux:heading>
                <flux:subheading>The unit is soft-deleted and the action is audited.</flux:subheading>
            </div>
            <p class="text-sm text-zinc-600 dark:text-zinc-300">
                Delete <span class="font-semibold">{{ $deletingName }}</span>? Units with departments, projects, or warehouses cannot be deleted.
            </p>
            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showDeleteModal', false)">Cancel</flux:button>
                <flux:button type="button" variant="danger" wire:click="delete">Delete business unit</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
