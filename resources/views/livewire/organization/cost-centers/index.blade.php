<div class="space-y-6">
    <x-page-header title="Cost Centres" description="Budget-owning units used by projects and stock issues.">
        <x-slot name="actions">
            @can('create', App\Domain\Organization\Models\CostCenter::class)
                <flux:button variant="primary" icon="plus" wire:click="openCreate">New cost centre</flux:button>
            @endcan
        </x-slot>
    </x-page-header>

    <x-domain-feedback :message="$feedback" :type="$feedbackType" />

    <flux:card>
        <div class="flex flex-wrap items-center gap-3">
            <flux:input wire:model.live.debounce.300ms="search" placeholder="Search cost centres" icon="magnifying-glass" class="max-w-sm" />
            <flux:spacer />
            <span class="text-sm text-zinc-500 dark:text-zinc-400">{{ $costCenters->total() }} cost centres</span>
        </div>

        <flux:table :paginate="$costCenters" class="mt-4">
            <flux:table.columns>
                <flux:table.column>Code</flux:table.column>
                <flux:table.column>Name</flux:table.column>
                <flux:table.column>Department</flux:table.column>
                <flux:table.column>Projects</flux:table.column>
                <flux:table.column>Status</flux:table.column>
                <flux:table.column align="end">Actions</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($costCenters as $costCenter)
                    <flux:table.row :key="$costCenter->id">
                        <flux:table.cell variant="strong">{{ $costCenter->code }}</flux:table.cell>
                        <flux:table.cell>{{ $costCenter->name }}</flux:table.cell>
                        <flux:table.cell>{{ $costCenter->department?->name ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $costCenter->projects_count }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$costCenter->is_active ? 'green' : 'zinc'">
                                {{ $costCenter->is_active ? 'Active' : 'Inactive' }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            <div class="flex justify-end gap-1">
                                @can('update', $costCenter)
                                    <flux:button size="sm" variant="ghost" icon="pencil-square" aria-label="Edit cost centre" wire:click="openEdit({{ $costCenter->id }})" />
                                @endcan
                                @can('delete', $costCenter)
                                    <flux:button size="sm" variant="ghost" icon="trash" aria-label="Delete cost centre" wire:click="confirmDelete({{ $costCenter->id }})" />
                                @endcan
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6" class="py-10 text-center text-zinc-500 dark:text-zinc-400">
                            No cost centres match your search.
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <flux:modal wire:model="showForm" class="md:w-[32rem]">
        <form wire:submit="save" class="space-y-4">
            <div>
                <flux:heading size="lg">{{ $editingId ? 'Edit cost centre' : 'New cost centre' }}</flux:heading>
                <flux:subheading>Cost centres are standalone master data and may optionally reference a department.</flux:subheading>
            </div>

            <flux:select wire:model="form.department_id" label="Department (optional)">
                <option value="">No department</option>
                @foreach ($departments as $department)
                    <option value="{{ $department->id }}">{{ $department->name }} ({{ $department->code }})</option>
                @endforeach
            </flux:select>

            <flux:input wire:model="form.code" label="Code" placeholder="e.g. CC-TECH-01" :disabled="$editingId !== null" required />
            <flux:input wire:model="form.name" label="Name" placeholder="e.g. Technical Operations" required />
            <flux:textarea wire:model="form.description" label="Description" rows="3" />
            <flux:switch wire:model="form.is_active" label="Active" />

            <div class="flex justify-end gap-2 pt-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showForm', false)">Cancel</flux:button>
                <flux:button type="submit" variant="primary">{{ $editingId ? 'Save changes' : 'Create cost centre' }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model="showDeleteModal" class="md:w-96">
        <div class="space-y-4">
            <div>
                <flux:heading size="lg">Delete cost centre</flux:heading>
                <flux:subheading>Cost centres referenced by projects cannot be deleted.</flux:subheading>
            </div>
            <p class="text-sm text-zinc-600 dark:text-zinc-300">
                Delete <span class="font-semibold">{{ $deletingName }}</span>?
            </p>
            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showDeleteModal', false)">Cancel</flux:button>
                <flux:button type="button" variant="danger" wire:click="delete">Delete cost centre</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
