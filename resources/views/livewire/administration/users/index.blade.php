<div class="space-y-6">
    <x-page-header title="Users" description="Accounts, roles, departmental placement, and warehouse access.">
        <x-slot name="actions">
            @can('create', App\Models\User::class)
                <flux:button variant="primary" icon="plus" wire:click="openCreate">New user</flux:button>
            @endcan
        </x-slot>
    </x-page-header>

    <x-domain-feedback :message="$feedback" :type="$feedbackType" />

    <flux:card>
        <div class="flex flex-wrap items-center gap-3">
            <flux:input wire:model.live.debounce.300ms="search" placeholder="Search name, email, or employee number" icon="magnifying-glass" class="max-w-sm" />
            <flux:select wire:model.live="statusFilter" class="max-w-40">
                <option value="">All statuses</option>
                @foreach ($statusOptions as $status)
                    <option value="{{ $status->value }}">{{ $status->label() }}</option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="roleFilter" class="max-w-48">
                <option value="">All roles</option>
                @foreach ($roleOptions as $roleName)
                    <option value="{{ $roleName }}">{{ $roleName }}</option>
                @endforeach
            </flux:select>
            <flux:spacer />
            <span class="text-sm text-zinc-500 dark:text-zinc-400">{{ $users->total() }} users</span>
        </div>

        <flux:table :paginate="$users" class="mt-4">
            <flux:table.columns>
                <flux:table.column>User</flux:table.column>
                <flux:table.column>Roles</flux:table.column>
                <flux:table.column>Department</flux:table.column>
                <flux:table.column>Status</flux:table.column>
                <flux:table.column>Last login</flux:table.column>
                <flux:table.column align="end">Actions</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($users as $user)
                    <flux:table.row :key="$user->id">
                        <flux:table.cell>
                            <a href="{{ route('administration.users.show', $user) }}" wire:navigate class="font-medium text-zinc-800 hover:underline dark:text-zinc-100">
                                {{ $user->name }}
                            </a>
                            <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ $user->email }}</div>
                            @if ($user->employee_number)
                                <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ $user->employee_number }} · {{ $user->job_title }}</div>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex flex-wrap gap-1">
                                @forelse ($user->roles as $role)
                                    <flux:badge size="sm" color="zinc">{{ $role->name }}</flux:badge>
                                @empty
                                    <span class="text-xs text-zinc-500 dark:text-zinc-400">No role</span>
                                @endforelse
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div>{{ $user->department?->name ?? '—' }}</div>
                            <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ $user->costCenter?->name ?? 'No cost centre' }}</div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="match ($user->status) {
                                App\Enums\UserStatus::Active => 'green',
                                App\Enums\UserStatus::Suspended => 'amber',
                                App\Enums\UserStatus::Inactive => 'zinc',
                            }">
                                {{ $user->status->label() }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            {{ $user->last_login_at?->diffForHumans() ?? 'Never' }}
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            <div class="flex justify-end gap-1">
                                @can('view', $user)
                                    <flux:button size="sm" variant="ghost" icon="eye" aria-label="Open user" :href="route('administration.users.show', $user)" wire:navigate />
                                @endcan
                                @can('update', $user)
                                    <flux:button size="sm" variant="ghost" icon="pencil-square" aria-label="Edit user" wire:click="openEdit({{ $user->id }})" />
                                @endcan
                                @can('delete', $user)
                                    <flux:button size="sm" variant="ghost" icon="user-minus" aria-label="Deactivate user" wire:click="confirmDeactivate({{ $user->id }})" />
                                @endcan
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6" class="py-10 text-center text-zinc-500 dark:text-zinc-400">
                            No users match your filters.
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <flux:modal wire:model="showForm" class="md:w-[48rem]">
        <form wire:submit="save" class="space-y-4">
            <div>
                <flux:heading size="lg">{{ $editingId ? 'Edit user' : 'New user' }}</flux:heading>
                <flux:subheading>Roles and warehouse access are applied immediately on save.</flux:subheading>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="form.name" label="Full name" required />
                <flux:input wire:model="form.email" type="email" label="Email" required />
                <flux:input wire:model="form.employee_number" label="Employee number" />
                <flux:input wire:model="form.phone" label="Phone" />
                <flux:input wire:model="form.job_title" label="Job title" />
                <flux:input wire:model="form.password" type="password" label="{{ $editingId ? 'New password (optional)' : 'Password (optional)' }}" description="Leave blank to generate a random password." />
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <flux:select wire:model="form.department_id" label="Department">
                    <option value="">None</option>
                    @foreach ($departments as $department)
                        <option value="{{ $department->id }}">{{ $department->name }}</option>
                    @endforeach
                </flux:select>

                <flux:select wire:model="form.cost_center_id" label="Cost centre">
                    <option value="">None</option>
                    @foreach ($costCenters as $costCenter)
                        <option value="{{ $costCenter->id }}">{{ $costCenter->name }}</option>
                    @endforeach
                </flux:select>

                <flux:select wire:model="form.status" label="Status">
                    @foreach ($statusOptions as $status)
                        <option value="{{ $status->value }}">{{ $status->label() }}</option>
                    @endforeach
                </flux:select>
            </div>

            <div>
                <div class="mb-2 text-sm font-medium text-zinc-700 dark:text-zinc-200">Roles</div>
                <div class="grid gap-2 sm:grid-cols-2">
                    @forelse ($roles as $role)
                        <flux:checkbox wire:model="form.roles" value="{{ $role->name }}" label="{{ $role->name }}" />
                    @empty
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">No roles are available for assignment.</p>
                    @endforelse
                </div>
            </div>

            <div>
                <div class="mb-2 text-sm font-medium text-zinc-700 dark:text-zinc-200">Warehouse access</div>
                <div class="grid gap-2 sm:grid-cols-2">
                    @forelse ($warehouses as $warehouse)
                        <flux:checkbox wire:model="form.warehouse_ids" value="{{ $warehouse->id }}" label="{{ $warehouse->code }} · {{ $warehouse->name }}" />
                    @empty
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">No warehouses are available for assignment.</p>
                    @endforelse
                </div>
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showForm', false)">Cancel</flux:button>
                <flux:button type="submit" variant="primary">{{ $editingId ? 'Save changes' : 'Create user' }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model="showDeactivateModal" class="md:w-96">
        <div class="space-y-4">
            <div>
                <flux:heading size="lg">Deactivate user</flux:heading>
                <flux:subheading>The account is marked inactive and soft-deleted. Historical records are retained.</flux:subheading>
            </div>
            <p class="text-sm text-zinc-600 dark:text-zinc-300">
                Deactivate <span class="font-semibold">{{ $deactivatingName }}</span>?
            </p>
            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showDeactivateModal', false)">Cancel</flux:button>
                <flux:button type="button" variant="danger" wire:click="deactivate">Deactivate user</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
