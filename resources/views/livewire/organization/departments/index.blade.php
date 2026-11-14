<div class="space-y-6">
    <x-page-header title="Departments" description="Standalone functional departments used across the organization.">
        <x-slot name="actions">
            @can('create', App\Domain\Organization\Models\Department::class)
                <flux:button variant="primary" icon="plus" wire:click="openCreate">New department</flux:button>
            @endcan
        </x-slot>
    </x-page-header>

    <x-domain-feedback :message="$feedback" :type="$feedbackType" />

    <flux:card>
        <div class="flex flex-wrap items-center gap-3">
            <flux:input wire:model.live.debounce.300ms="search" placeholder="Search departments" icon="magnifying-glass" class="max-w-sm" />
            <flux:spacer />
            <span class="text-sm text-zinc-500 dark:text-zinc-400">{{ $departments->total() }} departments</span>
        </div>

        <flux:table :paginate="$departments" class="mt-4">
            <flux:table.columns>
                <flux:table.column>Code</flux:table.column>
                <flux:table.column>Name</flux:table.column>
                <flux:table.column>Cost centres</flux:table.column>
                <flux:table.column>Status</flux:table.column>
                <flux:table.column align="end">Actions</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($departments as $department)
                    <flux:table.row :key="$department->id">
                        <flux:table.cell variant="strong">{{ $department->code }}</flux:table.cell>
                        <flux:table.cell>
                            <div class="font-medium text-zinc-800 dark:text-zinc-100">{{ $department->name }}</div>
                            @if ($department->description)
                                <div class="max-w-xs truncate text-xs text-zinc-500 dark:text-zinc-400">{{ $department->description }}</div>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>{{ $department->cost_centers_count }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$department->is_active ? 'green' : 'zinc'">
                                {{ $department->is_active ? 'Active' : 'Inactive' }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            <div class="flex justify-end gap-1">
                                @can('update', $department)
                                    <flux:button size="sm" variant="ghost" icon="pencil-square" aria-label="Edit department" wire:click="openEdit({{ $department->id }})" />
                                @endcan
                                @can('delete', $department)
                                    <flux:button size="sm" variant="ghost" icon="trash" aria-label="Delete department" wire:click="confirmDelete({{ $department->id }})" />
                                @endcan
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5" class="py-10 text-center text-zinc-500 dark:text-zinc-400">
                            No departments match your search.
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <flux:modal wire:model="showForm" class="md:w-[32rem]">
        <form wire:submit="save" class="space-y-4">
            <div>
                <flux:heading size="lg">{{ $editingId ? 'Edit department' : 'New department' }}</flux:heading>
                <flux:subheading>Departments are standalone master data used across all business units.</flux:subheading>
            </div>

            <flux:input wire:model="form.code" label="Code" placeholder="e.g. DEPT-TECH" :disabled="$editingId !== null" required />
            <flux:input wire:model="form.name" label="Name" placeholder="e.g. Technical Services" required />
            <flux:textarea wire:model="form.description" label="Description" rows="3" />
            <flux:switch wire:model="form.is_active" label="Active" />

            <div class="flex justify-end gap-2 pt-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showForm', false)">Cancel</flux:button>
                <flux:button type="submit" variant="primary">{{ $editingId ? 'Save changes' : 'Create department' }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model="showDeleteModal" class="md:w-96">
        <div class="space-y-4">
            <div>
                <flux:heading size="lg">Delete department</flux:heading>
                <flux:subheading>Departments with cost centres or projects cannot be deleted.</flux:subheading>
            </div>
            <p class="text-sm text-zinc-600 dark:text-zinc-300">
                Delete <span class="font-semibold">{{ $deletingName }}</span>?
            </p>
            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showDeleteModal', false)">Cancel</flux:button>
                <flux:button type="button" variant="danger" wire:click="delete">Delete department</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
