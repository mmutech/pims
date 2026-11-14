<div class="space-y-6">
    <x-page-header :title="$returnId ? 'Edit material return' : 'New material return'" description="Returns are tied to a posted stock issue. Condition assessment is mandatory before posting.">
        <x-slot name="actions">
            <flux:button variant="ghost" icon="arrow-left" :href="route('distribution.returns.index')" wire:navigate>All returns</flux:button>
        </x-slot>
    </x-page-header>

    <x-domain-feedback :message="$feedback" :type="$feedbackType" />

    <form wire:submit="save" class="space-y-6">
        <flux:card class="space-y-4">
            <flux:heading>Return details</flux:heading>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <flux:select wire:model.live="form.original_issue_id" label="Original stock issue" required>
                    <option value="">Select posted issue</option>
                    @foreach ($issues as $issue)
                        <option value="{{ $issue->id }}">{{ $issue->document_number }}@if ($issue->requisition) — {{ $issue->requisition->document_number }}@endif</option>
                    @endforeach
                </flux:select>
                <flux:select wire:model.live="form.return_type" label="Return type" required>
                    <option value="project">Project</option>
                    <option value="department">Department</option>
                </flux:select>
                <flux:select wire:model="form.warehouse_id" label="Warehouse" required>
                    <option value="">Select warehouse</option>
                    @foreach ($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}">{{ $warehouse->code }} — {{ $warehouse->name }}</option>
                    @endforeach
                </flux:select>
                <flux:select wire:model="form.returned_by" label="Returned by" required>
                    <option value="">Select user</option>
                    @foreach ($users as $user)
                        <option value="{{ $user->id }}">{{ $user->name }}</option>
                    @endforeach
                </flux:select>
            </div>

            <flux:textarea wire:model="form.reason" label="Reason" rows="3" description="Explain why the material is being returned." required />
        </flux:card>

        <flux:card class="space-y-4">
            <div>
                <flux:heading>Returnable lines</flux:heading>
                <flux:subheading>Lines are limited to the quantity issued less any previous returns.</flux:subheading>
            </div>

            @if ($items === [])
                <flux:callout variant="warning" icon="information-circle" heading="Select a posted stock issue">
                    Choose the original stock issue to load the quantities that are still returnable.
                </flux:callout>
            @else
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>Item</flux:table.column>
                        <flux:table.column>Unit</flux:table.column>
                        <flux:table.column>Batch</flux:table.column>
                        <flux:table.column align="end">Returnable</flux:table.column>
                        <flux:table.column align="end">Return now</flux:table.column>
                        <flux:table.column>Condition</flux:table.column>
                        <flux:table.column>Notes</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($items as $index => $item)
                            <flux:table.row :key="$item['item_id']">
                                <flux:table.cell>
                                    <div class="font-medium">{{ $item['item'] }}</div>
                                    <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ $item['code'] }}</div>
                                </flux:table.cell>
                                <flux:table.cell>{{ $item['unit'] }}</flux:table.cell>
                                <flux:table.cell>
                                    @if ($item['requires_batch'] && $item['batches'] !== [])
                                        <flux:select wire:model="items.{{ $index }}.batch_id" placeholder="Select batch">
                                            @foreach ($item['batches'] as $batch)
                                                <option value="{{ $batch['id'] }}">{{ $batch['label'] }}</option>
                                            @endforeach
                                        </flux:select>
                                    @elseif ($item['batch_id'] !== '')
                                        <span class="text-sm">#{{ $item['batch_id'] }}</span>
                                    @else
                                        <span class="text-zinc-500 dark:text-zinc-400">Not tracked</span>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell align="end">{{ rtrim(rtrim($item['max'], '0'), '.') }}</flux:table.cell>
                                <flux:table.cell align="end">
                                    <flux:input wire:model="items.{{ $index }}.returned_quantity" type="number" step="0.0001" min="0" class="ml-auto w-28" />
                                </flux:table.cell>
                                <flux:table.cell>
                                    <flux:select wire:model="items.{{ $index }}.condition">
                                        @foreach ($conditions as $condition)
                                            <option value="{{ $condition->value }}">{{ $condition->label() }}</option>
                                        @endforeach
                                    </flux:select>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <flux:input wire:model="items.{{ $index }}.notes" placeholder="Optional" />
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endif
        </flux:card>

        <div class="flex justify-end gap-2">
            <flux:button variant="ghost" :href="route('distribution.returns.index')" wire:navigate>Cancel</flux:button>
            <flux:button type="submit" variant="primary" :disabled="$items === []">{{ $returnId ? 'Save changes' : 'Create draft' }}</flux:button>
        </div>
    </form>
</div>
