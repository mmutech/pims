<div class="space-y-6">
    <x-page-header title="Requests for Quotation" description="Competitive sourcing events issued to approved suppliers.">
        <x-slot name="actions">
            @can('create', App\Domain\Procurement\Models\RequestForQuotation::class)
                <flux:button variant="primary" icon="plus" :href="route('procurement.rfqs.create')" wire:navigate>New RFQ</flux:button>
            @endcan
        </x-slot>
    </x-page-header>

    <x-domain-feedback :message="$feedback" :type="$feedbackType" />

    <flux:card>
        <div class="flex flex-wrap items-center gap-3">
            <flux:input wire:model.live.debounce.300ms="search" placeholder="Search number or title" icon="magnifying-glass" class="max-w-sm" />
            <flux:select wire:model.live="statusFilter" class="max-w-44">
                <option value="">All statuses</option>
                @foreach ($statusOptions as $status)
                    <option value="{{ $status->value }}">{{ $status->label() }}</option>
                @endforeach
            </flux:select>
            <flux:spacer />
            <span class="text-sm text-zinc-500 dark:text-zinc-400">{{ $rfqs->total() }} RFQs</span>
        </div>

        <flux:table :paginate="$rfqs" class="mt-4">
            <flux:table.columns>
                <flux:table.column>Number</flux:table.column>
                <flux:table.column>Title</flux:table.column>
                <flux:table.column>Requisition</flux:table.column>
                <flux:table.column>Closing</flux:table.column>
                <flux:table.column align="end">Lines</flux:table.column>
                <flux:table.column align="end">Suppliers</flux:table.column>
                <flux:table.column align="end">Quotes</flux:table.column>
                <flux:table.column>Status</flux:table.column>
                <flux:table.column align="end">Actions</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($rfqs as $rfq)
                    <flux:table.row :key="$rfq->id">
                        <flux:table.cell variant="strong">
                            <a href="{{ route('procurement.rfqs.show', $rfq) }}" wire:navigate class="hover:underline">{{ $rfq->document_number }}</a>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div>{{ $rfq->title }}</div>
                            <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ $rfq->businessUnit?->name }}</div>
                        </flux:table.cell>
                        <flux:table.cell>{{ $rfq->purchaseRequisition?->document_number ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $rfq->closing_date?->format('d M Y') }}</flux:table.cell>
                        <flux:table.cell align="end">{{ $rfq->items_count }}</flux:table.cell>
                        <flux:table.cell align="end">{{ $rfq->suppliers_count }}</flux:table.cell>
                        <flux:table.cell align="end">{{ $rfq->quotations_count }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="match ($rfq->status) {
                                App\Enums\RequestForQuotationStatus::Draft => 'zinc',
                                App\Enums\RequestForQuotationStatus::Issued => 'blue',
                                App\Enums\RequestForQuotationStatus::Closed => 'amber',
                                App\Enums\RequestForQuotationStatus::Awarded => 'green',
                                App\Enums\RequestForQuotationStatus::Cancelled => 'zinc',
                            }">{{ $rfq->status->label() }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            <div class="flex justify-end gap-1">
                                @can('view', $rfq)
                                    <flux:button size="sm" variant="ghost" icon="eye" aria-label="Open RFQ" :href="route('procurement.rfqs.show', $rfq)" wire:navigate />
                                @endcan
                                @can('update', $rfq)
                                    <flux:button size="sm" variant="ghost" icon="pencil-square" aria-label="Edit RFQ" :href="route('procurement.rfqs.edit', $rfq)" wire:navigate />
                                @endcan
                                @can('delete', $rfq)
                                    <flux:button size="sm" variant="ghost" icon="trash" aria-label="Delete RFQ" wire:click="delete({{ $rfq->id }})" wire:confirm="Cancel and delete this draft RFQ?" />
                                @endcan
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="9" class="py-10 text-center text-zinc-500 dark:text-zinc-400">
                            No RFQs match your filters.
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>
</div>
