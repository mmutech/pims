<div class="space-y-6">
    <x-page-header :title="$countId ? 'Edit stock count' : 'New stock count'" description="Define the count scope and tolerances. Starting the count freezes an on-hand snapshot and opens it for counting.">
        <x-slot name="actions">
            <flux:button variant="ghost" icon="arrow-left" :href="route('stocktaking.counts.index')" wire:navigate>All counts</flux:button>
        </x-slot>
    </x-page-header>

    <x-domain-feedback :message="$feedback" :type="$feedbackType" />

    <form wire:submit="save" class="space-y-6">
        <flux:card class="space-y-4">
            <flux:heading>Count details</flux:heading>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <flux:select wire:model.live="form.warehouse_id" label="Warehouse" required>
                    <option value="">Select warehouse</option>
                    @foreach ($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}">{{ $warehouse->code }} — {{ $warehouse->name }}</option>
                    @endforeach
                </flux:select>
                <flux:select wire:model.live="form.count_type" label="Count type" required>
                    @foreach ($types as $type)
                        <option value="{{ $type->value }}">{{ $type->label() }}</option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="form.tolerance_quantity" type="number" step="0.0001" min="0" label="Quantity tolerance" description="Variances above this are flagged." />
                <flux:input wire:model="form.tolerance_value" type="number" step="0.01" min="0" label="Value tolerance" description="Variance value above this is flagged." />
            </div>

            <flux:textarea wire:model="form.scope_description" label="Scope description" rows="2" description="Describe the area, cycle, or reason for this count." required />
        </flux:card>

        <flux:card class="space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <flux:heading>Count scope</flux:heading>
                    <flux:subheading>A blank scope covers every balance in the warehouse. Each scope row is combined with OR.</flux:subheading>
                </div>
                <flux:button type="button" size="sm" icon="plus" wire:click="addScope">Add scope</flux:button>
            </div>

            <div class="space-y-3">
                @foreach ($scopes as $index => $scope)
                    <div class="grid items-end gap-3 rounded-lg border border-zinc-200 p-3 sm:grid-cols-12 dark:border-zinc-700" wire:key="stock-count-scope-{{ $index }}">
                        <flux:select wire:model="scopes.{{ $index }}.warehouse_location_id" label="Location" class="sm:col-span-4">
                            <option value="">Any location</option>
                            @foreach ($locations as $location)
                                <option value="{{ $location->id }}">{{ $location->code }} — {{ $location->name }}</option>
                            @endforeach
                        </flux:select>
                        <flux:select wire:model="scopes.{{ $index }}.item_category_id" label="Category" class="sm:col-span-3">
                            <option value="">Any category</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}">{{ $category->code }} — {{ $category->name }}</option>
                            @endforeach
                        </flux:select>
                        <flux:select wire:model="scopes.{{ $index }}.item_id" label="Specific item" class="sm:col-span-3">
                            <option value="">Any item</option>
                            @foreach ($catalogItems as $catalogItem)
                                <option value="{{ $catalogItem->id }}">{{ $catalogItem->code }} — {{ $catalogItem->name }}</option>
                            @endforeach
                        </flux:select>
                        <div class="sm:col-span-2">
                            <flux:checkbox wire:model="scopes.{{ $index }}.include_zero_balances" label="Include zero balances" />
                        </div>
                        <div class="flex justify-end sm:col-span-12">
                            <flux:button type="button" size="sm" variant="ghost" icon="trash" aria-label="Remove scope" wire:click="removeScope({{ $index }})" />
                        </div>
                    </div>
                @endforeach
            </div>
        </flux:card>

        <flux:card class="space-y-2">
            <flux:heading>Blind counts</flux:heading>
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                Blind counts hide system quantities from counters while the count is open. Counters can record quantities but
                cannot create adjustments; the user who scheduled the count is also barred from adjusting its variances.
            </flux:text>
        </flux:card>

        <div class="flex justify-end gap-2">
            <flux:button variant="ghost" :href="route('stocktaking.counts.index')" wire:navigate>Cancel</flux:button>
            <flux:button type="submit" variant="primary">{{ $countId ? 'Save changes' : 'Create draft' }}</flux:button>
        </div>
    </form>
</div>
