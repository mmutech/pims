<div class="space-y-6">
    <x-page-header title="Warehouses" description="Storage sites, transit points, and quarantine areas in the distribution network.">
        <x-slot name="actions">
            @can('create', App\Domain\Warehouse\Models\Warehouse::class)
                <flux:button variant="primary" icon="plus" wire:click="openCreate">New warehouse</flux:button>
            @endcan
        </x-slot>
    </x-page-header>

    <x-domain-feedback :message="$feedback" :type="$feedbackType" />

    <flux:card>
        <div class="flex flex-wrap items-center gap-3">
            <flux:input wire:model.live.debounce.300ms="search" placeholder="Search by code, name, or city" icon="magnifying-glass" class="max-w-sm" />
            <flux:select wire:model.live="typeFilter" class="max-w-48">
                <option value="">All types</option>
                @foreach ($typeOptions as $type)
                    <option value="{{ $type->value }}">{{ $type->label() }}</option>
                @endforeach
            </flux:select>
            <flux:spacer />
            <span class="text-sm text-zinc-500 dark:text-zinc-400">{{ $warehouses->total() }} warehouses</span>
        </div>

        <flux:table :paginate="$warehouses" class="mt-4">
            <flux:table.columns>
                <flux:table.column>Code</flux:table.column>
                <flux:table.column>Warehouse</flux:table.column>
                <flux:table.column>Business unit</flux:table.column>
                <flux:table.column>Type</flux:table.column>
                <flux:table.column>Locations</flux:table.column>
                <flux:table.column>Users</flux:table.column>
                <flux:table.column>Status</flux:table.column>
                <flux:table.column align="end">Actions</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($warehouses as $warehouse)
                    <flux:table.row :key="$warehouse->id">
                        <flux:table.cell variant="strong">{{ $warehouse->code }}</flux:table.cell>
                        <flux:table.cell>
                            <a href="{{ route('warehouses.show', $warehouse) }}" wire:navigate class="font-medium text-zinc-800 hover:underline dark:text-zinc-100">
                                {{ $warehouse->name }}
                            </a>
                            <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ $warehouse->city ?? 'No city' }}</div>
                        </flux:table.cell>
                        <flux:table.cell>{{ $warehouse->businessUnit?->name }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" color="zinc">{{ $warehouse->type->label() }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>{{ $warehouse->locations_count }}</flux:table.cell>
                        <flux:table.cell>{{ $warehouse->access_grants_count }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$warehouse->is_active ? 'green' : 'zinc'">
                                {{ $warehouse->is_active ? 'Active' : 'Inactive' }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            <div class="flex justify-end gap-1">
                                @can('view', $warehouse)
                                    <flux:button size="sm" variant="ghost" icon="eye" aria-label="Open warehouse" :href="route('warehouses.show', $warehouse)" wire:navigate />
                                @endcan
                                @can('update', $warehouse)
                                    <flux:button size="sm" variant="ghost" icon="pencil-square" aria-label="Edit warehouse" wire:click="openEdit({{ $warehouse->id }})" />
                                @endcan
                                @can('delete', $warehouse)
                                    <flux:button size="sm" variant="ghost" icon="trash" aria-label="Delete warehouse" wire:click="confirmDelete({{ $warehouse->id }})" />
                                @endcan
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="8" class="py-10 text-center text-zinc-500 dark:text-zinc-400">
                            No warehouses match your filters.
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <flux:modal wire:model="showForm" class="md:w-[44rem]">
        <form wire:submit="save" class="space-y-4">
            <div>
                <flux:heading size="lg">{{ $editingId ? 'Edit warehouse' : 'New warehouse' }}</flux:heading>
                <flux:subheading>Warehouse codes are immutable. The assigned manager receives Manage access automatically.</flux:subheading>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:select wire:model="form.business_unit_id" label="Business unit" required>
                    <option value="">Select a business unit</option>
                    @foreach ($businessUnits as $businessUnit)
                        <option value="{{ $businessUnit->id }}">{{ $businessUnit->name }}</option>
                    @endforeach
                </flux:select>

                <flux:input wire:model="form.code" label="Code" placeholder="e.g. WH-IKJ-01" :disabled="$editingId !== null" required />
                <flux:input wire:model="form.name" label="Name" placeholder="e.g. Ikeja Central Store" required />

                <flux:select wire:model="form.type" label="Type">
                    @foreach ($typeOptions as $type)
                        <option value="{{ $type->value }}">{{ $type->label() }}</option>
                    @endforeach
                </flux:select>

                <flux:select wire:model="form.manager_id" label="Warehouse manager">
                    <option value="">Unassigned</option>
                    @foreach ($managers as $manager)
                        <option value="{{ $manager->id }}">{{ $manager->name }}</option>
                    @endforeach
                </flux:select>

                <flux:input wire:model="form.city" label="City" />
                <flux:input wire:model="form.state" label="State" />
                <flux:input wire:model="form.country" label="Country" />
                <flux:input wire:model="form.postal_code" label="Postal code" />
                <flux:input wire:model="form.phone" label="Phone" />
                <flux:input wire:model="form.email" label="Email" type="email" />
            </div>

            <flux:textarea wire:model="form.address" label="Address" rows="2" />
            <flux:switch wire:model="form.is_active" label="Active" description="Inactive warehouses cannot receive or issue stock." />

            <div class="flex justify-end gap-2 pt-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showForm', false)">Cancel</flux:button>
                <flux:button type="submit" variant="primary">{{ $editingId ? 'Save changes' : 'Create warehouse' }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model="showDeleteModal" class="md:w-96">
        <div class="space-y-4">
            <div>
                <flux:heading size="lg">Delete warehouse</flux:heading>
                <flux:subheading>Warehouses with locations or access grants cannot be deleted.</flux:subheading>
            </div>
            <p class="text-sm text-zinc-600 dark:text-zinc-300">
                Delete <span class="font-semibold">{{ $deletingName }}</span>?
            </p>
            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showDeleteModal', false)">Cancel</flux:button>
                <flux:button type="button" variant="danger" wire:click="delete">Delete warehouse</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
