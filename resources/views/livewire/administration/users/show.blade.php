<div class="space-y-6">
    <x-page-header :title="$user->name" :description="'User account · '.$user->email">
        <x-slot name="actions">
            <flux:button variant="ghost" icon="arrow-left" :href="route('administration.users.index')" wire:navigate>All users</flux:button>
        </x-slot>
    </x-page-header>

    <x-domain-feedback :message="$feedback" :type="$feedbackType" />

    <div class="grid gap-6 lg:grid-cols-3">
        <flux:card class="lg:col-span-2">
            <div class="flex items-center justify-between">
                <flux:heading>Profile</flux:heading>
                <flux:badge size="sm" :color="match ($user->status) {
                    App\Enums\UserStatus::Active => 'green',
                    App\Enums\UserStatus::Suspended => 'amber',
                    App\Enums\UserStatus::Inactive => 'zinc',
                }">
                    {{ $user->status->label() }}
                </flux:badge>
            </div>

            <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Employee number</dt>
                    <dd class="mt-1 text-sm text-zinc-800 dark:text-zinc-100">{{ $user->employee_number ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Job title</dt>
                    <dd class="mt-1 text-sm text-zinc-800 dark:text-zinc-100">{{ $user->job_title ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Department</dt>
                    <dd class="mt-1 text-sm text-zinc-800 dark:text-zinc-100">{{ $user->department?->name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Cost centre</dt>
                    <dd class="mt-1 text-sm text-zinc-800 dark:text-zinc-100">{{ $user->costCenter?->name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Phone</dt>
                    <dd class="mt-1 text-sm text-zinc-800 dark:text-zinc-100">{{ $user->phone ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Last login</dt>
                    <dd class="mt-1 text-sm text-zinc-800 dark:text-zinc-100">{{ $user->last_login_at?->format('d M Y H:i') ?? 'Never' }}</dd>
                </div>
            </dl>
        </flux:card>

        <flux:card>
            <flux:heading>Account status</flux:heading>
            <flux:subheading>Inactive and suspended users cannot sign in.</flux:subheading>

            @can('update', $user)
                <form wire:submit="changeStatus" class="mt-4 space-y-3">
                    <flux:select wire:model="statusForm" label="Status">
                        @foreach ($statusOptions as $status)
                            <option value="{{ $status->value }}">{{ $status->label() }}</option>
                        @endforeach
                    </flux:select>
                    <flux:button type="submit" variant="primary" class="w-full">Update status</flux:button>
                </form>
            @endcan
        </flux:card>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <flux:card>
            <flux:heading>Roles</flux:heading>
            <flux:subheading>Permissions are inherited from every assigned role.</flux:subheading>

            @can('assignRoles', $user)
                <form wire:submit="saveRoles" class="mt-4 space-y-3">
                    <div class="grid gap-2 sm:grid-cols-2">
                        @forelse ($assignableRoles as $role)
                            <flux:checkbox wire:model="roleForm" value="{{ $role->name }}" label="{{ $role->name }}" />
                        @empty
                            <p class="text-sm text-zinc-500 dark:text-zinc-400">No roles are available for assignment.</p>
                        @endforelse
                    </div>
                    <div class="flex justify-end">
                        <flux:button type="submit" variant="primary">Save roles</flux:button>
                    </div>
                </form>
            @else
                <div class="mt-4 flex flex-wrap gap-1">
                    @forelse ($user->roles as $role)
                        <flux:badge size="sm" color="zinc">{{ $role->name }}</flux:badge>
                    @empty
                        <span class="text-sm text-zinc-500 dark:text-zinc-400">No role assigned.</span>
                    @endforelse
                </div>
            @endcan
        </flux:card>

        <flux:card>
            <flux:heading>Warehouse access</flux:heading>
            <flux:subheading>Explicit grants allow the user to view and operate in a warehouse.</flux:subheading>

            @can('manageWarehouseAccess', $user)
                <form wire:submit="saveWarehouseAccess" class="mt-4 space-y-3">
                    <div class="grid max-h-64 gap-2 overflow-y-auto pe-1 sm:grid-cols-2">
                        @forelse ($warehouses as $warehouse)
                            <flux:checkbox wire:model="warehouseForm" value="{{ $warehouse->id }}" label="{{ $warehouse->code }} · {{ $warehouse->name }}" />
                        @empty
                            <p class="text-sm text-zinc-500 dark:text-zinc-400">No warehouses are available for assignment.</p>
                        @endforelse
                    </div>
                    <div class="flex justify-end">
                        <flux:button type="submit" variant="primary">Save access</flux:button>
                    </div>
                </form>
            @else
                <div class="mt-4 space-y-2">
                    @forelse ($grants as $grant)
                        <div class="flex items-center justify-between gap-3 text-sm">
                            <span class="text-zinc-800 dark:text-zinc-100">{{ $grant->warehouse?->name }}</span>
                            <flux:badge size="sm" color="zinc">{{ $grant->access_level->label() }}</flux:badge>
                        </div>
                    @empty
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">No warehouse access granted.</p>
                    @endforelse
                </div>
            @endcan
        </flux:card>
    </div>

    <flux:card>
        <flux:heading>Account activity</flux:heading>
        <div class="mt-4 space-y-2">
            @forelse ($activity as $entry)
                <div class="flex items-center justify-between gap-3 border-b border-zinc-100 pb-2 text-sm last:border-0 last:pb-0 dark:border-zinc-700">
                    <div>
                        <span class="font-medium text-zinc-800 dark:text-zinc-100">{{ $entry->description }}</span>
                        <span class="text-zinc-500 dark:text-zinc-400">by {{ $entry->causer?->name ?? 'System' }}</span>
                    </div>
                    <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ $entry->created_at?->diffForHumans() }}</span>
                </div>
            @empty
                <p class="py-4 text-sm text-zinc-500 dark:text-zinc-400">No activity recorded for this account.</p>
            @endforelse
        </div>
    </flux:card>
</div>
