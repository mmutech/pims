<div class="space-y-6">
    <x-page-header :title="$quotationId ? 'Edit quotation' : 'Capture supplier quotation'" description="Record supplier pricing against an issued RFQ. Totals are recalculated with fixed precision.">
        <x-slot name="actions">
            <flux:button variant="ghost" icon="arrow-left" :href="route('procurement.quotations.index')" wire:navigate>All quotations</flux:button>
        </x-slot>
    </x-page-header>

    <x-domain-feedback :message="$feedback" :type="$feedbackType" />

    <form wire:submit="save" class="space-y-6">
        <flux:card class="space-y-4">
            <flux:heading>Quotation details</flux:heading>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @if ($quotationId === null)
                    <flux:select wire:model.live="rfqId" label="Request for quotation" required>
                        <option value="">Select an RFQ</option>
                        @foreach ($openRfqs as $option)
                            <option value="{{ $option->id }}">{{ $option->document_number }} — {{ $option->title }}</option>
                        @endforeach
                    </flux:select>
                @else
                    <div>
                        <flux:label>Request for quotation</flux:label>
                        <div class="mt-2 text-sm font-medium">{{ $rfq?->document_number ?? '—' }}</div>
                    </div>
                @endif

                <flux:select wire:model="form.supplier_id" label="Supplier" required>
                    <option value="">Select a supplier</option>
                    @foreach ($quotableSuppliers as $supplier)
                        <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                    @endforeach
                </flux:select>

                <flux:input wire:model="form.currency" label="Currency" maxlength="3" required />
                <flux:input wire:model="form.valid_until" type="date" label="Valid until" />
                <flux:input wire:model="form.lead_time_days" type="number" min="0" label="Lead time (days)" />
                <flux:input wire:model="form.payment_terms" label="Payment terms" />
            </div>

            <flux:textarea wire:model="form.warranty_terms" label="Warranty terms" rows="2" />
        </flux:card>

        <flux:card class="space-y-4">
            <div>
                <flux:heading>Pricing lines</flux:heading>
                <flux:subheading>Enter a unit price for each RFQ item. Tax is applied to the net line value.</flux:subheading>
            </div>

            @if ($items === [])
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">Select an RFQ to load its items.</flux:text>
            @else
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
                        @foreach ($items as $index => $item)
                            @php($rfqItem = $rfq?->items->firstWhere('id', (int) $item['rfq_item_id']))
                            @php($lineTotal = \App\Support\Decimal::multiply((string) $item['quantity'], (string) $item['unit_price'], \App\Support\Decimal::MONEY_SCALE))
                            <flux:table.row :key="$index">
                                <flux:table.cell>
                                    <div class="font-medium">{{ $rfqItem?->item?->name ?? 'Unknown item' }}</div>
                                    <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ $rfqItem?->item?->code }}@if ($rfqItem?->specification) · {{ $rfqItem->specification }}@endif</div>
                                </flux:table.cell>
                                <flux:table.cell>{{ $rfqItem?->unitOfMeasure?->code ?? '—' }}</flux:table.cell>
                                <flux:table.cell align="end" class="w-32">
                                    <flux:input wire:model="items.{{ $index }}.quantity" type="number" step="0.0001" min="0" class="text-right" />
                                </flux:table.cell>
                                <flux:table.cell align="end" class="w-32">
                                    <flux:input wire:model="items.{{ $index }}.unit_price" type="number" step="0.0001" min="0" class="text-right" />
                                </flux:table.cell>
                                <flux:table.cell align="end" class="w-24">
                                    <flux:input wire:model="items.{{ $index }}.tax_rate" type="number" step="0.0001" min="0" max="100" class="text-right" />
                                </flux:table.cell>
                                <flux:table.cell align="end" class="w-32">
                                    <flux:input wire:model="items.{{ $index }}.discount_amount" type="number" step="0.01" min="0" class="text-right" />
                                </flux:table.cell>
                                <flux:table.cell align="end">{{ number_format((float) $lineTotal, 2) }}</flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endif
        </flux:card>

        <div class="flex justify-end gap-2">
            <flux:button variant="ghost" :href="route('procurement.quotations.index')" wire:navigate>Cancel</flux:button>
            <flux:button type="submit" variant="primary">{{ $quotationId ? 'Save changes' : 'Save quotation' }}</flux:button>
        </div>
    </form>
</div>
