<div class="space-y-6">
    <x-page-header :title="$requisitionId ? 'Edit material requisition' : 'New material requisition'" description="Request stock from a warehouse. Submission routes the document through the approval engine.">
        <x-slot name="actions">
            <flux:button variant="ghost" icon="arrow-left" :href="route('distribution.requisitions.index')" wire:navigate>All requisitions</flux:button>
        </x-slot>
    </x-page-header>

    <x-domain-feedback :message="$feedback" :type="$feedbackType" />

    <form wire:submit="save" class="space-y-6">
        <flux:card class="space-y-4">
            <flux:heading>Requisition details</flux:heading>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <flux:select wire:model.live="form.requesting_department_id" label="Requesting department" required>
                    <option value="">Select department</option>
                    @foreach ($departments as $department)
                        <option value="{{ $department->id }}">{{ $department->name }}</option>
                    @endforeach
                </flux:select>
                <flux:select wire:model.live="form.destination_warehouse_id" label="Destination warehouse" required>
                    <option value="">Select warehouse</option>
                    @foreach ($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}">{{ $warehouse->code }} — {{ $warehouse->name }}</option>
                    @endforeach
                </flux:select>
                <flux:select wire:model="form.cost_center_id" label="Cost centre">
                    <option value="">Not specified</option>
                    @foreach ($costCenters as $costCenter)
                        <option value="{{ $costCenter->id }}">{{ $costCenter->name }}</option>
                    @endforeach
                </flux:select>
                <flux:select wire:model="form.project_id" label="Project">
                    <option value="">Not project-funded</option>
                    @foreach ($projects as $project)
                        <option value="{{ $project->id }}">{{ $project->name }}</option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="form.required_by" type="date" label="Required by" />
                <flux:select wire:model="form.priority" label="Priority">
                    <option value="low">Low</option>
                    <option value="normal">Normal</option>
                    <option value="high">High</option>
                    <option value="urgent">Urgent</option>
                </flux:select>
            </div>

            <flux:textarea wire:model="form.purpose" label="Purpose" rows="3" description="Explain the operational need and where the material will be used." required />
        </flux:card>

        <flux:card class="space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <flux:heading>Lines</flux:heading>
                    <flux:subheading>Duplicate items are rejected; combine quantities on one line.</flux:subheading>
                </div>
                <flux:button type="button" size="sm" icon="plus" wire:click="addItem">Add line</flux:button>
            </div>

            <div class="space-y-3">
                @foreach ($items as $index => $item)
                    <div class="grid items-end gap-3 rounded-lg border border-zinc-200 p-3 sm:grid-cols-12 dark:border-zinc-700" wire:key="material-requisition-item-{{ $index }}">
                        <flux:select wire:model.live="items.{{ $index }}.item_id" label="Item" class="sm:col-span-4" required>
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
                        <flux:input wire:model="items.{{ $index }}.requested_quantity" type="number" step="0.0001" min="0" label="Quantity" class="sm:col-span-2" required />
                        <div class="flex justify-end sm:col-span-2">
                            <flux:button type="button" size="sm" variant="ghost" icon="trash" aria-label="Remove line" wire:click="removeItem({{ $index }})" />
                        </div>
                        <flux:input wire:model="items.{{ $index }}.notes" label="Notes" class="sm:col-span-12" />
                    </div>
                @endforeach
            </div>
        </flux:card>

        <div class="flex justify-end gap-2">
            <flux:button variant="ghost" :href="route('distribution.requisitions.index')" wire:navigate>Cancel</flux:button>
            <flux:button type="submit" variant="primary">{{ $requisitionId ? 'Save changes' : 'Create draft' }}</flux:button>
        </div>
    </form>
</div>
