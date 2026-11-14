<div class="space-y-6">
    <x-page-header title="Suppliers" description="Vendors registered for procurement, contracts, and material supply.">
        <x-slot name="actions">
            @can('create', App\Domain\Supplier\Models\Supplier::class)
                <flux:button variant="primary" icon="plus" wire:click="openCreate">Register supplier</flux:button>
            @endcan
        </x-slot>
    </x-page-header>

    <x-domain-feedback :message="$feedback" :type="$feedbackType" />

    <flux:card>
        <div class="flex flex-wrap items-center gap-3">
            <flux:input wire:model.live.debounce.300ms="search" placeholder="Search name, code, TIN, or email" icon="magnifying-glass" class="max-w-sm" />
            <flux:select wire:model.live="statusFilter" class="max-w-44">
                <option value="">All statuses</option>
                @foreach ($statusOptions as $status)
                    <option value="{{ $status->value }}">{{ $status->label() }}</option>
                @endforeach
            </flux:select>
            <flux:spacer />
            <span class="text-sm text-zinc-500 dark:text-zinc-400">{{ $suppliers->total() }} suppliers</span>
        </div>

        <flux:table :paginate="$suppliers" class="mt-4">
            <flux:table.columns>
                <flux:table.column>Code</flux:table.column>
                <flux:table.column>Supplier</flux:table.column>
                <flux:table.column>Contact</flux:table.column>
                <flux:table.column>Rating</flux:table.column>
                <flux:table.column>Approved</flux:table.column>
                <flux:table.column>Status</flux:table.column>
                <flux:table.column align="end">Actions</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($suppliers as $supplier)
                    <flux:table.row :key="$supplier->id">
                        <flux:table.cell variant="strong">{{ $supplier->code }}</flux:table.cell>
                        <flux:table.cell>
                            <a href="{{ route('suppliers.show', $supplier) }}" wire:navigate class="font-medium text-zinc-800 hover:underline dark:text-zinc-100">
                                {{ $supplier->name }}
                            </a>
                            <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                {{ $supplier->city ?? 'No city' }}@if ($supplier->tax_identification_number) · TIN {{ $supplier->tax_identification_number }}@endif
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div>{{ $supplier->contact_person ?? '—' }}</div>
                            <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ $supplier->email ?? $supplier->phone }}</div>
                        </flux:table.cell>
                        <flux:table.cell>{{ $supplier->rating !== null ? number_format((float) $supplier->rating, 2).' / 5' : '—' }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$supplier->is_approved ? 'green' : 'amber'">
                                {{ $supplier->is_approved ? 'Approved' : 'Pending' }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="match ($supplier->status) {
                                App\Enums\SupplierStatus::Active => 'green',
                                App\Enums\SupplierStatus::Pending => 'amber',
                                App\Enums\SupplierStatus::Suspended => 'orange',
                                App\Enums\SupplierStatus::Blacklisted => 'red',
                                App\Enums\SupplierStatus::Inactive => 'zinc',
                            }">
                                {{ $supplier->status->label() }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            <div class="flex justify-end gap-1">
                                @can('approve', $supplier)
                                    @if (! $supplier->is_approved && $supplier->status !== App\Enums\SupplierStatus::Blacklisted)
                                        <flux:button size="sm" variant="ghost" icon="check-badge" aria-label="Approve supplier" wire:click="approve({{ $supplier->id }})" wire:confirm="Approve this supplier?" />
                                    @endif
                                @endcan
                                @can('view', $supplier)
                                    <flux:button size="sm" variant="ghost" icon="eye" aria-label="Open supplier" :href="route('suppliers.show', $supplier)" wire:navigate />
                                @endcan
                                @can('update', $supplier)
                                    <flux:button size="sm" variant="ghost" icon="arrow-path" aria-label="Change status" wire:click="openStatusModal({{ $supplier->id }})" />
                                    <flux:button size="sm" variant="ghost" icon="pencil-square" aria-label="Edit supplier" wire:click="openEdit({{ $supplier->id }})" />
                                @endcan
                                @can('delete', $supplier)
                                    <flux:button size="sm" variant="ghost" icon="trash" aria-label="Delete supplier" wire:click="confirmDelete({{ $supplier->id }})" />
                                @endcan
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7" class="py-10 text-center text-zinc-500 dark:text-zinc-400">
                            No suppliers match your filters.
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <flux:modal wire:model="showForm" class="md:w-[46rem]">
        <form wire:submit="save" class="space-y-4">
            <div>
                <flux:heading size="lg">{{ $editingId ? 'Edit supplier' : 'Register supplier' }}</flux:heading>
                <flux:subheading>Supplier codes are immutable. New suppliers start as Pending until approved.</flux:subheading>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="form.code" label="Code" placeholder="e.g. SUP-0001" :disabled="$editingId !== null" required />
                <flux:input wire:model="form.name" label="Trading name" required />
                <flux:input wire:model="form.legal_name" label="Legal name" />
                <flux:input wire:model="form.tax_identification_number" label="TIN" />
                <flux:input wire:model="form.registration_number" label="Registration number" />
                <flux:input wire:model="form.website" label="Website" placeholder="https://" />
                <flux:input wire:model="form.email" type="email" label="Email" />
                <flux:input wire:model="form.phone" label="Phone" />
                <flux:input wire:model="form.contact_person" label="Contact person" />
                <flux:input wire:model="form.city" label="City" />
                <flux:input wire:model="form.state" label="State" />
                <flux:input wire:model="form.country" label="Country" />
                <flux:input wire:model="form.postal_code" label="Postal code" />
            </div>

            <flux:textarea wire:model="form.address" label="Address" rows="2" />
            <flux:textarea wire:model="form.notes" label="Notes" rows="2" />

            <div class="flex justify-end gap-2 pt-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showForm', false)">Cancel</flux:button>
                <flux:button type="submit" variant="primary">{{ $editingId ? 'Save changes' : 'Register supplier' }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model="showStatusModal" class="md:w-96">
        <form wire:submit="changeStatus" class="space-y-4">
            <div>
                <flux:heading size="lg">Change supplier status</flux:heading>
                <flux:subheading>{{ $statusSupplierName }}</flux:subheading>
            </div>

            <flux:select wire:model="newStatus" label="New status" required>
                @foreach ($statusOptions as $status)
                    <option value="{{ $status->value }}">{{ $status->label() }}</option>
                @endforeach
            </flux:select>

            <flux:textarea wire:model="statusReason" label="Reason" rows="2" description="Required when suspending or blacklisting." />

            <div class="flex justify-end gap-2 pt-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showStatusModal', false)">Cancel</flux:button>
                <flux:button type="submit" variant="primary">Update status</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model="showDeleteModal" class="md:w-96">
        <div class="space-y-4">
            <div>
                <flux:heading size="lg">Archive supplier</flux:heading>
                <flux:subheading>The supplier is set to inactive and soft-deleted. Suppliers referenced by batches cannot be archived.</flux:subheading>
            </div>
            <p class="text-sm text-zinc-600 dark:text-zinc-300">
                Archive <span class="font-semibold">{{ $deletingName }}</span>?
            </p>
            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showDeleteModal', false)">Cancel</flux:button>
                <flux:button type="button" variant="danger" wire:click="delete">Archive supplier</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
