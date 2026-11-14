<div class="space-y-6">
    <x-page-header title="Roles & Permissions" description="Role-based access control. System roles are locked; custom roles can be tailored.">
        <x-slot name="actions">
            @can('create', Spatie\Permission\Models\Role::class)
                <flux:button variant="primary" icon="plus" wire:click="openCreate">New role</flux:button>
            @endcan
        </x-slot>
    </x-page-header>

    <x-domain-feedback :message="$feedback" :type="$feedbackType" />

    <flux:card>
        <div class="flex flex-wrap items-center gap-3">
            <flux:input wire:model.live.debounce.300ms="search" placeholder="Search roles" icon="magnifying-glass" class="max-w-sm" />
            <flux:spacer />
            <span class="text-sm text-zinc-500 dark:text-zinc-400">{{ $roles->total() }} roles</span>
        </div>

        <flux:table :paginate="$roles" class="mt-4">
            <flux:table.columns>
                <flux:table.column>Role</flux:table.column>
                <flux:table.column>Permissions</flux:table.column>
                <flux:table.column>Users</flux:table.column>
                <flux:table.column>Type</flux:table.column>
                <flux:table.column align="end">Actions</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($roles as $role)
                    <flux:table.row :key="$role->id">
                        <flux:table.cell variant="strong">{{ $role->name }}</flux:table.cell>
                        <flux:table.cell>{{ $role->permissions_count }}</flux:table.cell>
                        <flux:table.cell>{{ $role->users_count }}</flux:table.cell>
                        <flux:table.cell>
                            @if (in_array($role->name, $systemRoles, true))
                                <flux:badge size="sm" color="orange">System</flux:badge>
                            @else
                                <flux:badge size="sm" color="zinc">Custom</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            <div class="flex justify-end gap-1">
                                @if (! in_array($role->name, $systemRoles, true))
                                    @can('update', $role)
                                        <flux:button size="sm" variant="ghost" icon="pencil-square" aria-label="Edit role" wire:click="openEdit({{ $role->id }})" />
                                    @endcan
                                    @can('delete', $role)
                                        <flux:button size="sm" variant="ghost" icon="trash" aria-label="Delete role" wire:click="confirmDelete({{ $role->id }})" />
                                    @endcan
                                @else
                                    <span class="text-xs text-zinc-500 dark:text-zinc-400">Managed by the system</span>
                                @endif
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5" class="py-10 text-center text-zinc-500 dark:text-zinc-400">
                            No roles match your search.
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <flux:modal wire:model="showForm" class="md:w-[48rem]">
        <form wire:submit="save" class="space-y-4">
            <div>
                <flux:heading size="lg">{{ $editingId ? 'Edit role' : 'New role' }}</flux:heading>
                <flux:subheading>Grant only the permissions required for the role's responsibilities.</flux:subheading>
            </div>

            <flux:input wire:model="form.name" label="Role name" placeholder="e.g. Regional Store Supervisor" required />

            <div class="max-h-96 space-y-4 overflow-y-auto rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                @foreach ($permissionGroups as $group => $permissions)
                    <div>
                        <div class="mb-2 text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ $group }}</div>
                        <div class="grid gap-2 sm:grid-cols-2">
                            @foreach ($permissions as $permission)
                                <flux:checkbox
                                    wire:model="form.permissions"
                                    value="{{ $permission }}"
                                    label="{{ str($permission)->replace('.', ' ')->headline() }}"
                                />
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showForm', false)">Cancel</flux:button>
                <flux:button type="submit" variant="primary">{{ $editingId ? 'Save changes' : 'Create role' }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model="showDeleteModal" class="md:w-96">
        <div class="space-y-4">
            <div>
                <flux:heading size="lg">Delete role</flux:heading>
                <flux:subheading>Roles assigned to users cannot be deleted.</flux:subheading>
            </div>
            <p class="text-sm text-zinc-600 dark:text-zinc-300">
                Delete <span class="font-semibold">{{ $deletingName }}</span>?
            </p>
            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showDeleteModal', false)">Cancel</flux:button>
                <flux:button type="button" variant="danger" wire:click="delete">Delete role</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
