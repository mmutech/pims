<div class="space-y-6">
    <x-page-header :title="$rfqId ? 'Edit RFQ' : 'New request for quotation'" description="Invite approved suppliers to quote on a defined package of items.">
        <x-slot name="actions">
            <flux:button variant="ghost" icon="arrow-left" :href="route('procurement.rfqs.index')" wire:navigate>All RFQs</flux:button>
        </x-slot>
    </x-page-header>

    <x-domain-feedback :message="$feedback" :type="$feedbackType" />

    <form wire:submit="save" class="space-y-6">
        <flux:card class="space-y-4">
            <flux:heading>RFQ details</flux:heading>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <flux:select wire:model.live="form.purchase_requisition_id" label="Source requisition">
                    <option value="">Not linked</option>
                    @foreach ($requisitions as $requisition)
                        <option value="{{ $requisition->id }}">{{ $requisition->document_number }}</option>
                    @endforeach
                </flux:select>
                <flux:select wire:model="form.business_unit_id" label="Business unit" required>
                    <option value="">Select business unit</option>
                    @foreach ($businessUnits as $businessUnit)
                        <option value="{{ $businessUnit->id }}">{{ $businessUnit->name }}</option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="form.closing_date" type="date" label="Closing date" required />
            </div>

            <flux:input wire:model="form.title" label="Title" required />
            <flux:textarea wire:model="form.description" label="Description" rows="2" />
        </flux:card>

        <flux:card class="space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <flux:heading>Lines</flux:heading>
                    <flux:subheading>Linked requisition lines cannot exceed their remaining approved balance.</flux:subheading>
                </div>
                <flux:button type="button" size="sm" icon="plus" wire:click="addItem">Add line</flux:button>
            </div>

            <div class="space-y-3">
                @foreach ($items as $index => $item)
                    <div class="grid items-end gap-3 rounded-lg border border-zinc-200 p-3 sm:grid-cols-12 dark:border-zinc-700" wire:key="rfq-item-{{ $index }}">
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
                        <flux:input wire:model="items.{{ $index }}.quantity" type="number" step="0.0001" min="0" label="Quantity" class="sm:col-span-2" required />
                        <div class="flex justify-end sm:col-span-4">
                            <flux:button type="button" size="sm" variant="ghost" icon="trash" aria-label="Remove line" wire:click="removeItem({{ $index }})" />
                        </div>
                        <flux:input wire:model="items.{{ $index }}.specification" label="Specification / notes" class="sm:col-span-12" />
                    </div>
                @endforeach
            </div>
        </flux:card>

        <flux:card class="space-y-3">
            <div>
                <flux:heading>Invited suppliers</flux:heading>
                <flux:subheading>Only approved, active suppliers should be invited.</flux:subheading>
            </div>
            <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($suppliers as $supplier)
                    <label class="flex items-center gap-2 rounded-lg border border-zinc-200 p-2 text-sm dark:border-zinc-700">
                        <input type="checkbox" value="{{ $supplier->id }}" wire:model="supplierIds" class="rounded border-zinc-300">
                        <span>{{ $supplier->name }}</span>
                    </label>
                @endforeach
            </div>
        </flux:card>

        <div class="flex justify-end gap-2">
            <flux:button variant="ghost" :href="route('procurement.rfqs.index')" wire:navigate>Cancel</flux:button>
            <flux:button type="submit" variant="primary">{{ $rfqId ? 'Save changes' : 'Create draft' }}</flux:button>
        </div>
    </form>
</div>
