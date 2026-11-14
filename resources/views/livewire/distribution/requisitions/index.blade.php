<div class="space-y-6">
    <x-page-header title="Material Requisitions" description="Raise demand for warehouse stock and track approval, reservation, issuance, and acknowledgement.">
        <x-slot name="actions">
            @can('create', App\Domain\Distribution\Models\MaterialRequisition::class)
                <flux:button variant="primary" icon="plus" :href="route('distribution.requisitions.create')" wire:navigate>New requisition</flux:button>
            @endcan
        </x-slot>
    </x-page-header>

    <x-domain-feedback :message="$feedback" :type="$feedbackType" />

    <flux:card>
        <div class="flex flex-wrap items-center gap-3">
            <flux:input wire:model.live.debounce.300ms="search" placeholder="Search number, purpose or department" icon="magnifying-glass" class="max-w-sm" />
            <flux:select wire:model.live="statusFilter" class="max-w-48">
                <option value="">All statuses</option>
                @foreach ($statusOptions as $status)
                    <option value="{{ $status->value }}">{{ $status->label() }}</option>
                @endforeach
            </flux:select>
            <flux:spacer />
            <span class="text-sm text-zinc-500 dark:text-zinc-400">{{ $requisitions->total() }} requisitions</span>
        </div>

        <flux:table :paginate="$requisitions" class="mt-4">
            <flux:table.columns>
                <flux:table.column>Number</flux:table.column>
                <flux:table.column>Department</flux:table.column>
                <flux:table.column>Project</flux:table.column>
                <flux:table.column>Warehouse</flux:table.column>
                <flux:table.column>Required by</flux:table.column>
                <flux:table.column align="end">Lines</flux:table.column>
                <flux:table.column>Status</flux:table.column>
                <flux:table.column align="end">Actions</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($requisitions as $requisition)
                    <flux:table.row :key="$requisition->id">
                        <flux:table.cell variant="strong">
                            <a href="{{ route('distribution.requisitions.show', $requisition) }}" wire:navigate class="hover:underline">{{ $requisition->document_number }}</a>
                        </flux:table.cell>
                        <flux:table.cell>{{ $requisition->requestingDepartment?->name ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $requisition->project?->name ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $requisition->destinationWarehouse?->name ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $requisition->required_by?->format('d M Y') ?? '—' }}</flux:table.cell>
                        <flux:table.cell align="end">{{ $requisition->items_count }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="match ($requisition->status) {
                                App\Enums\RequisitionStatus::Draft => 'zinc',
                                App\Enums\RequisitionStatus::Submitted => 'blue',
                                App\Enums\RequisitionStatus::Approved => 'green',
                                App\Enums\RequisitionStatus::PartiallyApproved => 'lime',
                                App\Enums\RequisitionStatus::Rejected => 'red',
                                App\Enums\RequisitionStatus::Returned => 'amber',
                                App\Enums\RequisitionStatus::Reserved => 'purple',
                                App\Enums\RequisitionStatus::PartiallyIssued => 'purple',
                                App\Enums\RequisitionStatus::Issued => 'green',
                                App\Enums\RequisitionStatus::Acknowledged => 'green',
                                App\Enums\RequisitionStatus::Closed => 'zinc',
                                App\Enums\RequisitionStatus::Cancelled => 'zinc',
                            }">{{ $requisition->status->label() }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            <div class="flex justify-end gap-1">
                                <flux:button size="sm" variant="ghost" icon="eye" aria-label="Open requisition" :href="route('distribution.requisitions.show', $requisition)" wire:navigate />
                                @can('update', $requisition)
                                    <flux:button size="sm" variant="ghost" icon="pencil-square" aria-label="Edit requisition" :href="route('distribution.requisitions.edit', $requisition)" wire:navigate />
                                @endcan
                                @can('delete', $requisition)
                                    <flux:button size="sm" variant="ghost" icon="trash" aria-label="Delete requisition" wire:click="delete({{ $requisition->id }})" wire:confirm="Delete this draft requisition?" />
                                @endcan
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="8" class="py-10 text-center text-zinc-500 dark:text-zinc-400">
                            No material requisitions match your filters.
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>
</div>
