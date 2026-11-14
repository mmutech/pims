<div class="space-y-6">
    <x-page-header :title="$orderId ? 'Edit purchase order' : 'New purchase order'" description="Commit to a supplier against an approved requisition or accepted quotation.">
        <x-slot name="actions">
            <flux:button variant="ghost" icon="arrow-left" :href="route('procurement.orders.index')" wire:navigate>All purchase orders</flux:button>
        </x-slot>
    </x-page-header>

    <x-domain-feedback :message="$feedback" :type="$feedbackType" />

    @if (! $allowUnsourced && $form['purchase_requisition_id'] === '' && $form['supplier_quotation_id'] === '')
        <flux:callout variant="warning" icon="exclamation-triangle" heading="Sourcing required">
            Purchase orders must trace to an accepted quotation or an approved requisition. Select a source below or start from an approved document.
        </flux:callout>
    @endif

    <form wire:submit="save" class="space-y-6">
        <flux:card class="space-y-4">
            <flux:heading>Order details</flux:heading>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <flux:select wire:model.live="form.supplier_quotation_id" label="Accepted quotation">
                    <option value="">No quotation</option>
                    @foreach ($quotations as $quotation)
                        <option value="{{ $quotation->id }}">{{ $quotation->document_number }} — {{ $quotation->supplier?->name }}</option>
                    @endforeach
                </flux:select>
                <flux:select wire:model.live="form.purchase_requisition_id" label="Approved requisition">
                    <option value="">No requisition</option>
                    @foreach ($requisitions as $requisition)
                        <option value="{{ $requisition->id }}">{{ $requisition->document_number }}</option>
                    @endforeach
                </flux:select>
                <flux:select wire:model="form.supplier_id" label="Supplier" required>
                    <option value="">Select supplier</option>
                    @foreach ($suppliers as $supplier)
                        <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                    @endforeach
                </flux:select>
                <flux:select wire:model="form.delivery_warehouse_id" label="Delivery warehouse" required>
                    <option value="">Select warehouse</option>
                    @foreach ($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="form.currency" label="Currency" maxlength="3" required />
                <flux:input wire:model="form.payment_terms" label="Payment terms" />
                <flux:input wire:model="form.incoterms" label="Incoterms" maxlength="20" />
                <flux:input wire:model="form.expected_delivery_date" type="date" label="Expected delivery" />
            </div>
        </flux:card>

        <flux:card class="space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <flux:heading>Order lines</flux:heading>
                    <flux:subheading>Quantities and prices use fixed decimal precision. Approved amendments raise the order version.</flux:subheading>
                </div>
                <flux:button type="button" size="sm" icon="plus" wire:click="addItem">Add line</flux:button>
            </div>

            <div class="space-y-3">
                @foreach ($items as $index => $item)
                    @php($lineTotal = \App\Support\Decimal::multiply((string) $item['ordered_quantity'], (string) $item['unit_price'], \App\Support\Decimal::MONEY_SCALE))
                    <div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700" wire:key="order-item-{{ $index }}">
                        <div class="grid items-end gap-3 sm:grid-cols-12">
                            <flux:select wire:model="items.{{ $index }}.item_id" label="Item" class="sm:col-span-4" required>
                                <option value="">Select item</option>
                                @foreach ($catalogItems as $catalogItem)
                                    <option value="{{ $catalogItem->id }}">{{ $catalogItem->code }} — {{ $catalogItem->name }}</option>
                                @endforeach
                            </flux:select>
                            <flux:select wire:model="items.{{ $index }}.unit_of_measure_id" label="Unit" class="sm:col-span-2" required>
                                <option value="">Unit</option>
                                @foreach ($units as $unit)
                                    <option value="{{ $unit->id }}">{{ $unit->code }}</option>
                                @endforeach
                            </flux:select>
                            <flux:input wire:model="items.{{ $index }}.ordered_quantity" type="number" step="0.0001" min="0" label="Quantity" class="sm:col-span-2" required />
                            <flux:input wire:model="items.{{ $index }}.unit_price" type="number" step="0.0001" min="0" label="Unit price" class="sm:col-span-2" required />
                            <div class="flex justify-end sm:col-span-2">
                                <flux:button type="button" size="sm" variant="ghost" icon="trash" aria-label="Remove line" wire:click="removeItem({{ $index }})" />
                            </div>
                        </div>
                        <div class="mt-3 grid items-end gap-3 sm:grid-cols-12">
                            <flux:input wire:model="items.{{ $index }}.tax_rate" type="number" step="0.0001" min="0" max="100" label="Tax %" class="sm:col-span-2" required />
                            <flux:input wire:model="items.{{ $index }}.discount_amount" type="number" step="0.01" min="0" label="Discount" class="sm:col-span-2" required />
                            <flux:input wire:model="items.{{ $index }}.required_by" type="date" label="Required by" class="sm:col-span-2" />
                            <div class="sm:col-span-6">
                                <flux:label>Line total</flux:label>
                                <div class="mt-2 text-right font-mono text-sm font-medium">{{ number_format((float) $lineTotal, 2) }}</div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </flux:card>

        <div class="flex justify-end gap-2">
            <flux:button variant="ghost" :href="route('procurement.orders.index')" wire:navigate>Cancel</flux:button>
            <flux:button type="submit" variant="primary">{{ $orderId ? 'Save changes' : 'Create draft' }}</flux:button>
        </div>
    </form>
</div>
