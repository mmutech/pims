<div class="space-y-6">
    <x-page-header :title="$supplierReturn->document_number" :description="$supplierReturn->supplier?->name ?? 'Supplier return'">
        <x-slot name="actions">
            <flux:button variant="ghost" icon="arrow-left" :href="route('receiving.returns.index')" wire:navigate>All supplier returns</flux:button>

            @can('post', $supplierReturn)
                <flux:button variant="primary" icon="arrow-up-tray" wire:click="post" wire:confirm="Post this return out of stock? Availability is validated at posting.">Post return</flux:button>
            @endcan

            @can('cancel', $supplierReturn)
                <flux:button variant="danger" icon="x-circle" wire:click="cancel" wire:confirm="Cancel this draft supplier return?">Cancel</flux:button>
            @endcan
        </x-slot>
    </x-page-header>

    <x-domain-feedback :message="$feedback" :type="$feedbackType" />

    <div class="grid gap-6 lg:grid-cols-3">
        <flux:card class="space-y-4 lg:col-span-2">
            <div class="flex items-center justify-between">
                <flux:heading>Return summary</flux:heading>
                <flux:badge :color="match ($supplierReturn->status) {
                    App\Enums\ReturnStatus::Draft => 'zinc',
                    App\Enums\ReturnStatus::PendingInspection => 'blue',
                    App\Enums\ReturnStatus::Assessed => 'amber',
                    App\Enums\ReturnStatus::Posted => 'green',
                    App\Enums\ReturnStatus::Rejected => 'red',
                    App\Enums\ReturnStatus::Cancelled => 'red',
                }">{{ $supplierReturn->status->label() }}</flux:badge>
            </div>

            <dl class="grid gap-4 sm:grid-cols-2">
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Warehouse</dt>
                    <dd class="text-sm font-medium">{{ $supplierReturn->warehouse?->name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Goods received note</dt>
                    <dd class="text-sm font-medium">
                        @if ($supplierReturn->grn)
                            <a href="{{ route('receiving.grn.show', $supplierReturn->grn) }}" wire:navigate class="hover:underline">{{ $supplierReturn->grn->document_number }}</a>
                        @else
                            Not linked
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Return date</dt>
                    <dd class="text-sm font-medium">{{ $supplierReturn->return_date?->format('d M Y') ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Credit note</dt>
                    <dd class="text-sm font-medium">{{ $supplierReturn->credit_note_number ?: '—' }}</dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Reason</dt>
                    <dd class="text-sm font-medium">{{ $supplierReturn->reason }}</dd>
                </div>
            </dl>
        </flux:card>

        <flux:card class="space-y-3">
            <flux:heading>Posting</flux:heading>

            <dl class="space-y-2 text-sm">
                <div class="flex justify-between"><dt class="text-zinc-500 dark:text-zinc-400">Created by</dt><dd>{{ $supplierReturn->creator?->name ?? '—' }}</dd></div>
                <div class="flex justify-between"><dt class="text-zinc-500 dark:text-zinc-400">Posted at</dt><dd>{{ $supplierReturn->posted_at?->format('d M Y H:i') ?? 'Not posted' }}</dd></div>
                <div class="flex justify-between"><dt class="text-zinc-500 dark:text-zinc-400">Posted by</dt><dd>{{ $supplierReturn->poster?->name ?? '—' }}</dd></div>
            </dl>

            <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">
                Posting creates an outbound stock transaction. It fails if the available quantity is insufficient.
            </flux:text>
        </flux:card>
    </div>

    <flux:card class="space-y-4">
        <div>
            <flux:heading>Return lines</flux:heading>
            <flux:subheading>Quantities leave available stock when the return is posted.</flux:subheading>
        </div>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>Item</flux:table.column>
                <flux:table.column>Unit</flux:table.column>
                <flux:table.column align="end">Quantity</flux:table.column>
                <flux:table.column align="end">Unit cost</flux:table.column>
                <flux:table.column>Batch</flux:table.column>
                <flux:table.column>Serials</flux:table.column>
                <flux:table.column>Condition</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($supplierReturn->items as $item)
                    <flux:table.row :key="$item->id">
                        <flux:table.cell>
                            <div class="font-medium">{{ $item->item?->name }}</div>
                            <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ $item->item?->code }}</div>
                        </flux:table.cell>
                        <flux:table.cell>{{ $item->unitOfMeasure?->code }}</flux:table.cell>
                        <flux:table.cell align="end">{{ rtrim(rtrim($item->quantity, '0'), '.') }}</flux:table.cell>
                        <flux:table.cell align="end">{{ number_format((float) $item->unit_cost, 4) }}</flux:table.cell>
                        <flux:table.cell>{{ $item->batch?->batch_number ?? '—' }}</flux:table.cell>
                        <flux:table.cell>
                            @if (! empty($item->serial_numbers))
                                <span class="text-xs">{{ implode(', ', $item->serial_numbers) }}</span>
                            @else
                                —
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>{{ $item->condition->label() }}</flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </flux:card>
</div>
