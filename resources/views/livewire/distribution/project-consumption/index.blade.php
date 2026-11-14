<div class="space-y-6">
    <x-page-header title="Project Consumption" description="Posted stock issues grouped by project, with quantities and inventory value for project cost visibility." />

    <flux:card class="space-y-4">
        <form wire:submit="applyFilters" class="flex flex-wrap items-end gap-3">
            <flux:select wire:model="projectId" label="Project" class="min-w-64">
                <option value="">Select project</option>
                @foreach ($projects as $project)
                    <option value="{{ $project->id }}">{{ $project->code }} — {{ $project->name }}</option>
                @endforeach
            </flux:select>
            <flux:input wire:model="from" type="date" label="From" />
            <flux:input wire:model="to" type="date" label="To" />
            <flux:button type="submit" variant="primary" icon="funnel">Apply filters</flux:button>
        </form>
    </flux:card>

    @if ($summary !== null)
        <div class="grid gap-4 sm:grid-cols-2">
            <flux:card class="space-y-1">
                <flux:text class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Total quantity consumed</flux:text>
                <flux:heading size="xl">{{ rtrim(rtrim($summary['quantity'], '0'), '.') }}</flux:heading>
            </flux:card>
            <flux:card class="space-y-1">
                <flux:text class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Total issued value</flux:text>
                <flux:heading size="xl">{{ number_format((float) $summary['value'], 2) }}</flux:heading>
            </flux:card>
        </div>

        <flux:card class="space-y-4">
            <div>
                <flux:heading>Consumption by item</flux:heading>
                <flux:subheading>Only posted stock issues are included. Draft, cancelled, and reversed issues are excluded from posted values by ledger state.</flux:subheading>
            </div>

            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Item</flux:table.column>
                    <flux:table.column>Code</flux:table.column>
                    <flux:table.column align="end">Quantity</flux:table.column>
                    <flux:table.column align="end">Value</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($summary['lines'] as $line)
                        <flux:table.row :key="$line['item_id']">
                            <flux:table.cell variant="strong">{{ $line['item_name'] }}</flux:table.cell>
                            <flux:table.cell>{{ $line['item_code'] }}</flux:table.cell>
                            <flux:table.cell align="end">{{ rtrim(rtrim($line['quantity'], '0'), '.') }}</flux:table.cell>
                            <flux:table.cell align="end">{{ number_format((float) $line['value'], 2) }}</flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="4" class="py-10 text-center text-zinc-500 dark:text-zinc-400">
                                No posted consumption was found for this project in the selected period.
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </flux:card>
    @else
        <flux:callout icon="information-circle" heading="Choose a project">
            Select a project and optional date range to see its posted material consumption and cost.
        </flux:callout>
    @endif
</div>
