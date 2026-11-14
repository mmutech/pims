<div class="space-y-6">
    <x-page-header :title="$quotation->document_number" :description="$quotation->supplier?->name ?? 'Supplier quotation'">
        <x-slot name="actions">
            <flux:button variant="ghost" icon="arrow-left" :href="route('procurement.quotations.index')" wire:navigate>All quotations</flux:button>
            @can('update', $quotation)
                @if ($quotation->canBeEdited())
                    <flux:button icon="pencil-square" :href="route('procurement.quotations.edit', $quotation)" wire:navigate>Edit</flux:button>
                @endif
            @endcan
            @can('submit', $quotation)
                <flux:button variant="primary" icon="paper-airplane" wire:click="submit" wire:confirm="Submit this quotation? It becomes available for evaluation.">Submit</flux:button>
            @endcan
            @can('withdraw', $quotation)
                <flux:button icon="arrow-uturn-left" wire:click="withdraw" wire:confirm="Withdraw this quotation?">Withdraw</flux:button>
            @endcan
            @can('delete', $quotation)
                <flux:button variant="danger" icon="trash" wire:click="delete" wire:confirm="Delete this draft quotation?">Delete</flux:button>
            @endcan
        </x-slot>
    </x-page-header>

    <x-domain-feedback :message="$feedback" :type="$feedbackType" />

    <div class="grid gap-6 lg:grid-cols-3">
        <flux:card class="space-y-4 lg:col-span-2">
            <div class="flex items-center justify-between">
                <flux:heading>Quotation summary</flux:heading>
                <flux:badge :color="match ($quotation->status) {
                    App\Enums\SupplierQuotationStatus::Draft => 'zinc',
                    App\Enums\SupplierQuotationStatus::Submitted => 'blue',
                    App\Enums\SupplierQuotationStatus::Withdrawn => 'amber',
                    App\Enums\SupplierQuotationStatus::Accepted => 'green',
                    App\Enums\SupplierQuotationStatus::Rejected => 'red',
                }">{{ $quotation->status->label() }}</flux:badge>
            </div>

            <dl class="grid gap-4 sm:grid-cols-2">
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Supplier</dt>
                    <dd class="text-sm font-medium">{{ $quotation->supplier?->name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Request for quotation</dt>
                    <dd class="text-sm font-medium">
                        @if ($quotation->requestForQuotation)
                            <a href="{{ route('procurement.rfqs.show', $quotation->requestForQuotation) }}" wire:navigate class="hover:underline">{{ $quotation->requestForQuotation->document_number }}</a>
                        @else
                            —
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Valid until</dt>
                    <dd class="text-sm font-medium">{{ $quotation->valid_until?->format('d M Y') ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Lead time</dt>
                    <dd class="text-sm font-medium">{{ $quotation->lead_time_days !== null ? $quotation->lead_time_days.' days' : '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Payment terms</dt>
                    <dd class="text-sm font-medium">{{ $quotation->payment_terms ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Submitted</dt>
                    <dd class="text-sm font-medium">{{ $quotation->submitted_at?->format('d M Y H:i') ?? 'Not submitted' }}</dd>
                </div>
            </dl>

            @if ($quotation->warranty_terms)
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Warranty terms</dt>
                    <dd class="mt-1 text-sm">{{ $quotation->warranty_terms }}</dd>
                </div>
            @endif
        </flux:card>

        <flux:card class="space-y-4">
            <flux:heading>Totals</flux:heading>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between"><dt class="text-zinc-500 dark:text-zinc-400">Subtotal</dt><dd>{{ $quotation->currency }} {{ number_format((float) $quotation->subtotal, 2) }}</dd></div>
                <div class="flex justify-between"><dt class="text-zinc-500 dark:text-zinc-400">Tax</dt><dd>{{ $quotation->currency }} {{ number_format((float) $quotation->tax_amount, 2) }}</dd></div>
                <div class="flex justify-between border-t border-zinc-200 pt-2 text-base font-semibold dark:border-zinc-700"><dt>Total</dt><dd>{{ $quotation->currency }} {{ number_format((float) $quotation->total_amount, 2) }}</dd></div>
            </dl>
        </flux:card>
    </div>

    <flux:card class="space-y-4">
        <div>
            <flux:heading>Lines</flux:heading>
            <flux:subheading>Pricing captured against the RFQ item package.</flux:subheading>
        </div>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>Item</flux:table.column>
                <flux:table.column>Unit</flux:table.column>
                <flux:table.column align="end">Quantity</flux:table.column>
                <flux:table.column align="end">Unit price</flux:table.column>
                <flux:table.column align="end">Tax %</flux:table.column>
                <flux:table.column align="end">Discount</flux:table.column>
                <flux:table.column align="end">Line total</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($quotation->items as $item)
                    <flux:table.row :key="$item->id">
                        <flux:table.cell>
                            <div class="font-medium">{{ $item->item?->name }}</div>
                            <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ $item->item?->code }}@if ($item->rfqItem?->specification) · {{ $item->rfqItem->specification }}@endif</div>
                        </flux:table.cell>
                        <flux:table.cell>{{ $item->unitOfMeasure?->code }}</flux:table.cell>
                        <flux:table.cell align="end">{{ rtrim(rtrim($item->quantity, '0'), '.') }}</flux:table.cell>
                        <flux:table.cell align="end">{{ number_format((float) $item->unit_price, 4) }}</flux:table.cell>
                        <flux:table.cell align="end">{{ rtrim(rtrim($item->tax_rate, '0'), '.') }}</flux:table.cell>
                        <flux:table.cell align="end">{{ number_format((float) $item->discount_amount, 2) }}</flux:table.cell>
                        <flux:table.cell align="end">{{ number_format((float) $item->line_total, 2) }}</flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </flux:card>
</div>
