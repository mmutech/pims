<div class="space-y-6">
    <x-page-header title="Purchase Requisitions" description="Internal demand for goods and services, routed through approval before sourcing.">
        <x-slot name="actions">
            @can('create', App\Domain\Procurement\Models\PurchaseRequisition::class)
                <flux:button variant="primary" icon="plus" :href="route('procurement.requisitions.create')" wire:navigate>New requisition</flux:button>
            @endcan
        </x-slot>
    </x-page-header>

    <x-domain-feedback :message="$feedback" :type="$feedbackType" />

    <flux:card>
        <div class="flex flex-wrap items-center gap-3">
            <flux:input wire:model.live.debounce.300ms="search" placeholder="Search number, department, or justification" icon="magnifying-glass" class="max-w-sm" />
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
                <flux:table.column>Requester</flux:table.column>
                <flux:table.column>Required by</flux:table.column>
                <flux:table.column>Lines</flux:table.column>
                <flux:table.column>Estimated</flux:table.column>
                <flux:table.column>Status</flux:table.column>
                <flux:table.column align="end">Actions</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($requisitions as $requisition)
                    <flux:table.row :key="$requisition->id">
                        <flux:table.cell variant="strong">
                            <a href="{{ route('procurement.requisitions.show', $requisition) }}" wire:navigate class="hover:underline">
                                {{ $requisition->document_number }}
                            </a>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div>{{ $requisition->requestingDepartment?->name ?? '—' }}</div>
                            <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ $requisition->businessUnit?->name }}</div>
                        </flux:table.cell>
                        <flux:table.cell>{{ $requisition->requester?->name ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $requisition->required_by?->format('d M Y') ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $requisition->items_count }}</flux:table.cell>
                        <flux:table.cell>{{ $requisition->currency }} {{ number_format((float) $requisition->total_estimated_amount, 2) }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="match ($requisition->status) {
                                App\Enums\PurchaseRequisitionStatus::Draft => 'zinc',
                                App\Enums\PurchaseRequisitionStatus::Submitted => 'blue',
                                App\Enums\PurchaseRequisitionStatus::Approved => 'green',
                                App\Enums\PurchaseRequisitionStatus::PartiallyApproved => 'lime',
                                App\Enums\PurchaseRequisitionStatus::Rejected => 'red',
                                App\Enums\PurchaseRequisitionStatus::Returned => 'amber',
                                App\Enums\PurchaseRequisitionStatus::Converted => 'purple',
                                App\Enums\PurchaseRequisitionStatus::Cancelled => 'zinc',
                            }">{{ $requisition->status->label() }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            <div class="flex justify-end gap-1">
                                @can('view', $requisition)
                                    <flux:button size="sm" variant="ghost" icon="eye" aria-label="Open requisition" :href="route('procurement.requisitions.show', $requisition)" wire:navigate />
                                @endcan
                                @can('update', $requisition)
                                    <flux:button size="sm" variant="ghost" icon="pencil-square" aria-label="Edit requisition" :href="route('procurement.requisitions.edit', $requisition)" wire:navigate />
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
                            No requisitions match your filters.
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>
</div>
