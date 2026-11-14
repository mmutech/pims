<div class="space-y-6">
    <x-page-header :title="$warehouse->name" :description="'Warehouse '.$warehouse->code.' · '.($warehouse->businessUnit?->name ?? 'No business unit')">
        <x-slot name="actions">
            <flux:button variant="ghost" icon="arrow-left" :href="route('warehouses.index')" wire:navigate>All warehouses</flux:button>
        </x-slot>
    </x-page-header>

    <x-domain-feedback :message="$feedback" :type="$feedbackType" />

    <div class="grid gap-6 lg:grid-cols-3">
        <flux:card class="lg:col-span-2">
            <div class="flex items-center justify-between">
                <flux:heading>Warehouse details</flux:heading>
                <div class="flex items-center gap-2">
                    <flux:badge size="sm" color="zinc">{{ $warehouse->type->label() }}</flux:badge>
                    <flux:badge size="sm" :color="$warehouse->is_active ? 'green' : 'zinc'">
                        {{ $warehouse->is_active ? 'Active' : 'Inactive' }}
                    </flux:badge>
                </div>
            </div>

            <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Business unit</dt>
                    <dd class="mt-1 text-sm text-zinc-800 dark:text-zinc-100">{{ $warehouse->businessUnit?->name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Manager</dt>
                    <dd class="mt-1 text-sm text-zinc-800 dark:text-zinc-100">{{ $warehouse->manager?->name ?? 'Unassigned' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Phone</dt>
                    <dd class="mt-1 text-sm text-zinc-800 dark:text-zinc-100">{{ $warehouse->phone ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Email</dt>
                    <dd class="mt-1 text-sm text-zinc-800 dark:text-zinc-100">{{ $warehouse->email ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">City / State</dt>
                    <dd class="mt-1 text-sm text-zinc-800 dark:text-zinc-100">{{ collect([$warehouse->city, $warehouse->state])->filter()->implode(', ') ?: '—' }}</dd>
                </div>
            </dl>

            @if ($warehouse->address)
                <div class="mt-4 border-t border-zinc-100 pt-4 dark:border-zinc-700">
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Address</dt>
                    <dd class="mt-1 text-sm text-zinc-800 dark:text-zinc-100">{{ $warehouse->address }}</dd>
                </div>
            @endif
        </flux:card>

        <flux:card>
            <flux:heading>Structure</flux:heading>
            <div class="mt-4 space-y-3 text-sm">
                <div class="flex items-center justify-between">
                    <span class="text-zinc-500 dark:text-zinc-400">Locations</span>
                    <span class="font-medium text-zinc-800 dark:text-zinc-100">{{ $locations->count() }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-zinc-500 dark:text-zinc-400">Bins</span>
                    <span class="font-medium text-zinc-800 dark:text-zinc-100">{{ $locations->sum(fn ($location) => $location->bins->count()) }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-zinc-500 dark:text-zinc-400">Users with access</span>
                    <span class="font-medium text-zinc-800 dark:text-zinc-100">{{ $grants->count() }}</span>
                </div>
            </div>
        </flux:card>
    </div>

    <flux:card>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <flux:heading>Locations & bins</flux:heading>
                <flux:subheading>Locations group bins used to receive, store, and issue stock.</flux:subheading>
            </div>
            @can('create', App\Domain\Warehouse\Models\WarehouseLocation::class)
                <flux:button size="sm" variant="primary" icon="plus" wire:click="openCreateLocation">Add location</flux:button>
            @endcan
        </div>

        <div class="mt-4 space-y-4">
            @forelse ($locations as $location)
                <div class="rounded-lg border border-zinc-200 dark:border-zinc-700">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-zinc-200 bg-zinc-50 px-4 py-3 dark:border-zinc-700 dark:bg-zinc-800/50">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-semibold text-zinc-800 dark:text-zinc-100">{{ $location->code }}</span>
                                <span class="text-sm text-zinc-600 dark:text-zinc-300">{{ $location->name }}</span>
                                <flux:badge size="sm" color="zinc">{{ $location->type->label() }}</flux:badge>
                                @unless ($location->is_active)
                                    <flux:badge size="sm" color="orange">Inactive</flux:badge>
                                @endunless
                            </div>
                            @if ($location->description)
                                <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ $location->description }}</div>
                            @endif
                        </div>
                        <div class="flex items-center gap-1">
                            @can('create', App\Domain\Warehouse\Models\WarehouseBin::class)
                                <flux:button size="sm" variant="ghost" icon="plus" wire:click="openCreateBin({{ $location->id }})">Bin</flux:button>
                            @endcan
                            @can('update', $location)
                                <flux:button size="sm" variant="ghost" icon="pencil-square" aria-label="Edit location" wire:click="openEditLocation({{ $location->id }})" />
                            @endcan
                            @can('delete', $location)
                                <flux:button
                                    size="sm"
                                    variant="ghost"
                                    icon="trash"
                                    aria-label="Delete location"
                                    wire:click="deleteLocation({{ $location->id }})"
                                    wire:confirm="Delete this location? Locations with bins cannot be deleted."
                                />
                            @endcan
                        </div>
                    </div>

                    @if ($location->bins->isEmpty())
                        <p class="px-4 py-4 text-sm text-zinc-500 dark:text-zinc-400">No bins defined for this location.</p>
                    @else
                        <flux:table>
                            <flux:table.columns>
                                <flux:table.column>Bin</flux:table.column>
                                <flux:table.column>Name</flux:table.column>
                                <flux:table.column>Max capacity</flux:table.column>
                                <flux:table.column>Status</flux:table.column>
                                <flux:table.column align="end">Actions</flux:table.column>
                            </flux:table.columns>
                            <flux:table.rows>
                                @foreach ($location->bins as $bin)
                                    <flux:table.row :key="$bin->id">
                                        <flux:table.cell variant="strong">{{ $bin->code }}</flux:table.cell>
                                        <flux:table.cell>{{ $bin->name ?? '—' }}</flux:table.cell>
                                        <flux:table.cell>{{ $bin->max_capacity !== null ? $bin->max_capacity : '—' }}</flux:table.cell>
                                        <flux:table.cell>
                                            <flux:badge size="sm" :color="$bin->is_active ? 'green' : 'zinc'">
                                                {{ $bin->is_active ? 'Active' : 'Inactive' }}
                                            </flux:badge>
                                        </flux:table.cell>
                                        <flux:table.cell align="end">
                                            <div class="flex justify-end gap-1">
                                                @can('update', $bin)
                                                    <flux:button size="sm" variant="ghost" icon="pencil-square" aria-label="Edit bin" wire:click="openEditBin({{ $bin->id }})" />
                                                @endcan
                                                @can('delete', $bin)
                                                    <flux:button
                                                        size="sm"
                                                        variant="ghost"
                                                        icon="trash"
                                                        aria-label="Delete bin"
                                                        wire:click="deleteBin({{ $bin->id }})"
                                                        wire:confirm="Delete this bin?"
                                                    />
                                                @endcan
                                            </div>
                                        </flux:table.cell>
                                    </flux:table.row>
                                @endforeach
                            </flux:table.rows>
                        </flux:table>
                    @endif
                </div>
            @empty
                <p class="py-6 text-center text-sm text-zinc-500 dark:text-zinc-400">No locations defined yet.</p>
            @endforelse
        </div>
    </flux:card>

    <flux:card>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <flux:heading>Access grants</flux:heading>
                <flux:subheading>Only granted users may view or operate in this warehouse.</flux:subheading>
            </div>
            @can('manageAccess', $warehouse)
                <flux:button size="sm" variant="primary" icon="plus" wire:click="openAccessForm">Grant access</flux:button>
            @endcan
        </div>

        <flux:table class="mt-4">
            <flux:table.columns>
                <flux:table.column>User</flux:table.column>
                <flux:table.column>Level</flux:table.column>
                <flux:table.column>Primary</flux:table.column>
                <flux:table.column>Granted</flux:table.column>
                <flux:table.column align="end">Actions</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($grants as $grant)
                    <flux:table.row :key="$grant->id">
                        <flux:table.cell>
                            <div class="font-medium text-zinc-800 dark:text-zinc-100">{{ $grant->user?->name ?? 'Removed user' }}</div>
                            <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ $grant->user?->email }}</div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" color="zinc">{{ $grant->access_level->label() }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>{{ $grant->is_primary ? 'Yes' : 'No' }}</flux:table.cell>
                        <flux:table.cell>
                            <div>{{ $grant->granted_at?->format('d M Y') }}</div>
                            <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ $grant->grantedBy?->name }}</div>
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            @can('manageAccess', $warehouse)
                                <flux:button
                                    size="sm"
                                    variant="ghost"
                                    icon="x-mark"
                                    aria-label="Revoke access"
                                    wire:click="revokeAccess({{ $grant->id }})"
                                    wire:confirm="Revoke this user's warehouse access?"
                                />
                            @endcan
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5" class="py-8 text-center text-zinc-500 dark:text-zinc-400">
                            No access grants recorded.
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <flux:modal wire:model="showLocationForm" class="md:w-[32rem]">
        <form wire:submit="saveLocation" class="space-y-4">
            <div>
                <flux:heading size="lg">{{ $editingLocationId ? 'Edit location' : 'New location' }}</flux:heading>
                <flux:subheading>Location codes are unique within this warehouse.</flux:subheading>
            </div>

            <flux:input wire:model="locationForm.code" label="Code" placeholder="e.g. A-RACK" :disabled="$editingLocationId !== null" required />
            <flux:input wire:model="locationForm.name" label="Name" placeholder="e.g. Main Rack" required />
            <flux:select wire:model="locationForm.type" label="Type">
                @foreach ($locationTypeOptions as $type)
                    <option value="{{ $type->value }}">{{ $type->label() }}</option>
                @endforeach
            </flux:select>
            <flux:textarea wire:model="locationForm.description" label="Description" rows="2" />
            <flux:switch wire:model="locationForm.is_active" label="Active" />

            <div class="flex justify-end gap-2 pt-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showLocationForm', false)">Cancel</flux:button>
                <flux:button type="submit" variant="primary">{{ $editingLocationId ? 'Save changes' : 'Create location' }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model="showBinForm" class="md:w-96">
        <form wire:submit="saveBin" class="space-y-4">
            <div>
                <flux:heading size="lg">{{ $editingBinId ? 'Edit bin' : 'New bin' }}</flux:heading>
                <flux:subheading>{{ $binLocationName }}</flux:subheading>
            </div>

            <flux:input wire:model="binForm.code" label="Code" placeholder="e.g. B-01" :disabled="$editingBinId !== null" required />
            <flux:input wire:model="binForm.name" label="Name" placeholder="Optional label" />
            <flux:input wire:model="binForm.max_capacity" label="Maximum capacity" placeholder="Optional" />
            <flux:switch wire:model="binForm.is_active" label="Active" />

            <div class="flex justify-end gap-2 pt-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showBinForm', false)">Cancel</flux:button>
                <flux:button type="submit" variant="primary">{{ $editingBinId ? 'Save changes' : 'Create bin' }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model="showAccessForm" class="md:w-96">
        <form wire:submit="grantAccess" class="space-y-4">
            <div>
                <flux:heading size="lg">Grant warehouse access</flux:heading>
                <flux:subheading>{{ $warehouse->name }}</flux:subheading>
            </div>

            <flux:select wire:model="accessForm.user_id" label="User" required>
                <option value="">Select a user</option>
                @foreach ($users as $user)
                    <option value="{{ $user->id }}">{{ $user->name }} · {{ $user->email }}</option>
                @endforeach
            </flux:select>

            <flux:select wire:model="accessForm.access_level" label="Access level">
                @foreach ($accessLevelOptions as $level)
                    <option value="{{ $level->value }}">{{ $level->label() }}</option>
                @endforeach
            </flux:select>

            <flux:switch wire:model="accessForm.is_primary" label="Primary warehouse" description="Used as the user's default operating warehouse." />

            <div class="flex justify-end gap-2 pt-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showAccessForm', false)">Cancel</flux:button>
                <flux:button type="submit" variant="primary">Grant access</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
