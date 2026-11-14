<div class="space-y-6">
    <x-page-header title="Supplier Quotations" description="Prices submitted by invited suppliers against a request for quotation.">
        <x-slot name="actions">
            @can('create', App\Domain\Procurement\Models\SupplierQuotation::class)
                <flux:button variant="primary" icon="plus" :href="route('procurement.quotations.create')" wire:navigate>Capture quotation</flux:button>
            @endcan
        </x-slot>
    </x-page-header>

    <x-domain-feedback :message="$feedback" :type="$feedbackType" />

    <flux:card>
        <div class="flex flex-wrap items-center gap-3">
            <flux:input wire:model.live.debounce.300ms="search" placeholder="Search number or supplier" icon="magnifying-glass" class="max-w-sm" />
            <flux:select wire:model.live="statusFilter" class="max-w-44">
                <option value="">All statuses</option>
                @foreach ($statusOptions as $status)
                    <option value="{{ $status->value }}">{{ $status->label() }}</option>
                @endforeach
            </flux:select>
            <flux:spacer />
            <span class="text-sm text-zinc-500 dark:text-zinc-400">{{ $quotations->total() }} quotations</span>
        </div>

        <flux:table :paginate="$quotations" class="mt-4">
            <flux:table.columns>
                <flux:table.column>Number</flux:table.column>
                <flux:table.column>Supplier</flux:table.column>
                <flux:table.column>RFQ</flux:table.column>
                <flux:table.column>Valid until</flux:table.column>
                <flux:table.column align="end">Lines</flux:table.column>
                <flux:table.column align="end">Total</flux:table.column>
                <flux:table.column>Status</flux:table.column>
                <flux:table.column align="end">Actions</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($quotations as $quotation)
                    <flux:table.row :key="$quotation->id">
                        <flux:table.cell variant="strong">
                            <a href="{{ route('procurement.quotations.show', $quotation) }}" wire:navigate class="hover:underline">{{ $quotation->document_number }}</a>
                        </flux:table.cell>
                        <flux:table.cell>{{ $quotation->supplier?->name ?? '—' }}</flux:table.cell>
                        <flux:table.cell>
                            @if ($quotation->requestForQuotation)
                                <a href="{{ route('procurement.rfqs.show', $quotation->requestForQuotation) }}" wire:navigate class="text-sm hover:underline">{{ $quotation->requestForQuotation->document_number }}</a>
                            @else
                                —
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>{{ $quotation->valid_until?->format('d M Y') ?? '—' }}</flux:table.cell>
                        <flux:table.cell align="end">{{ $quotation->items_count }}</flux:table.cell>
                        <flux:table.cell align="end">{{ $quotation->currency }} {{ number_format((float) $quotation->total_amount, 2) }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="match ($quotation->status) {
                                App\Enums\SupplierQuotationStatus::Draft => 'zinc',
                                App\Enums\SupplierQuotationStatus::Submitted => 'blue',
                                App\Enums\SupplierQuotationStatus::Withdrawn => 'amber',
                                App\Enums\SupplierQuotationStatus::Accepted => 'green',
                                App\Enums\SupplierQuotationStatus::Rejected => 'red',
                            }">{{ $quotation->status->label() }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            <div class="flex justify-end gap-1">
                                @can('view', $quotation)
                                    <flux:button size="sm" variant="ghost" icon="eye" aria-label="Open quotation" :href="route('procurement.quotations.show', $quotation)" wire:navigate />
                                @endcan
                                @can('update', $quotation)
                                    <flux:button size="sm" variant="ghost" icon="pencil-square" aria-label="Edit quotation" :href="route('procurement.quotations.edit', $quotation)" wire:navigate />
                                @endcan
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="8" class="py-10 text-center text-zinc-500 dark:text-zinc-400">
                            No quotations match your filters.
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>
</div>
