<div class="space-y-6">
    <x-page-header title="New stock issue" :description="$requisition ? 'Issuing against '.$requisition->document_number : 'Issue reserved stock against an approved material requisition.'">
        <x-slot name="actions">
            <flux:button variant="ghost" icon="arrow-left" :href="route('distribution.issues.index')" wire:navigate>All issues</flux:button>
        </x-slot>
    </x-page-header>

    <x-domain-feedback :message="$feedback" :type="$feedbackType" />

    @if (! $hasReservation)
        <flux:callout variant="warning" icon="exclamation-triangle" heading="No reserved stock available">
            This requisition has no open reservation with outstanding quantity. Reserve stock from the
            requisition before creating a stock issue.
            @if ($requisition)
                <flux:button class="mt-3" size="sm" :href="route('distribution.requisitions.show', $requisition)" wire:navigate>Open requisition</flux:button>
            @endif
        </flux:callout>
    @else
        <form wire:submit="save" class="space-y-6">
            <flux:card class="space-y-4">
                <flux:heading>Issue details</flux:heading>

                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <flux:select wire:model="form.issued_to" label="Issued to" required>
                        <option value="">Select recipient</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}">{{ $user->name }}</option>
                        @endforeach
                    </flux:select>
                    <flux:select wire:model="form.issued_by" label="Issued by" required>
                        <option value="">Select issuer</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}">{{ $user->name }}</option>
                        @endforeach
                    </flux:select>
                    <flux:input wire:model="form.issue_date" type="date" label="Issue date" required />
                    @if ($requisition)
                        <div>
                            <flux:text class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Warehouse</flux:text>
                            <div class="text-sm font-medium">{{ $requisition->destinationWarehouse?->name ?? '—' }}</div>
                        </div>
                    @endif
                </div>
            </flux:card>

            <flux:card class="space-y-4">
                <div>
                    <flux:heading>Reserved lines</flux:heading>
                    <flux:subheading>Only reserved quantities can be issued. Reduce a quantity to leave the balance reserved for later.</flux:subheading>
                </div>

                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>Item</flux:table.column>
                        <flux:table.column>Unit</flux:table.column>
                        <flux:table.column>Batch</flux:table.column>
                        <flux:table.column>Dimension</flux:table.column>
                        <flux:table.column align="end">Reserved</flux:table.column>
                        <flux:table.column align="end">Issue now</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($lines as $index => $line)
                            <flux:table.row :key="$line['stock_reservation_item_id']">
                                <flux:table.cell>
                                    <div class="font-medium">{{ $line['item'] }}</div>
                                    <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ $line['code'] }}</div>
                                </flux:table.cell>
                                <flux:table.cell>{{ $line['unit'] }}</flux:table.cell>
                                <flux:table.cell>{{ $line['batch'] }}</flux:table.cell>
                                <flux:table.cell>{{ $line['dimension'] }}</flux:table.cell>
                                <flux:table.cell align="end">{{ rtrim(rtrim($line['outstanding'], '0'), '.') }}</flux:table.cell>
                                <flux:table.cell align="end">
                                    <flux:input wire:model="lines.{{ $index }}.quantity" type="number" step="0.0001" min="0" class="ml-auto w-32" />
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </flux:card>

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" :href="route('distribution.issues.index')" wire:navigate>Cancel</flux:button>
                <flux:button type="submit" variant="primary">Create draft issue</flux:button>
            </div>
        </form>
    @endif
</div>
