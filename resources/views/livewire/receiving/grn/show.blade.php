<div class="space-y-6">
    <x-page-header :title="$grn->document_number" :description="$grn->supplier?->name ?? 'Goods received note'">
        <x-slot name="actions">
            <flux:button variant="ghost" icon="arrow-left" :href="route('receiving.grn.index')" wire:navigate>All goods received notes</flux:button>

            @can('post', $grn)
                <flux:button variant="primary" icon="check-badge" wire:click="post" wire:confirm="Post accepted quantities to stock? This cannot be edited afterwards.">Post to stock</flux:button>
            @endcan

            @can('cancel', $grn)
                <flux:button variant="danger" icon="x-circle" wire:click="cancel" wire:confirm="Cancel this draft goods received note?">Cancel</flux:button>
            @endcan

            @can('reverse', $grn)
                <flux:button variant="danger" icon="arrow-uturn-left" wire:click="$set('showReverseModal', true)">Reverse posting</flux:button>
            @endcan
        </x-slot>
    </x-page-header>

    <x-domain-feedback :message="$feedback" :type="$feedbackType" />

    <div class="grid gap-6 lg:grid-cols-3">
        <flux:card class="space-y-4 lg:col-span-2">
            <div class="flex items-center justify-between">
                <flux:heading>Receipt summary</flux:heading>
                <flux:badge :color="match ($grn->status) {
                    App\Enums\GoodsReceivedNoteStatus::Draft => 'zinc',
                    App\Enums\GoodsReceivedNoteStatus::Posted => 'green',
                    App\Enums\GoodsReceivedNoteStatus::Reversed => 'purple',
                    App\Enums\GoodsReceivedNoteStatus::Cancelled => 'red',
                }">{{ $grn->status->label() }}</flux:badge>
            </div>

            <dl class="grid gap-4 sm:grid-cols-2">
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Purchase order</dt>
                    <dd class="text-sm font-medium">
                        @if ($grn->purchaseOrder)
                            <a href="{{ route('procurement.orders.show', $grn->purchaseOrder) }}" wire:navigate class="hover:underline">{{ $grn->purchaseOrder->document_number }}</a>
                        @else
                            —
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Receipt advice</dt>
                    <dd class="text-sm font-medium">
                        @if ($grn->sra)
                            <a href="{{ route('receiving.sra.show', $grn->sra) }}" wire:navigate class="hover:underline">{{ $grn->sra->document_number }}</a>
                        @else
                            —
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Warehouse</dt>
                    <dd class="text-sm font-medium">{{ $grn->warehouse?->name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Received date</dt>
                    <dd class="text-sm font-medium">{{ $grn->received_date?->format('d M Y') ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Inspection</dt>
                    <dd class="text-sm font-medium">
                        @if ($grn->qualityInspection)
                            {{ $grn->qualityInspection->document_number }} ({{ $grn->qualityInspection->status->label() }})
                        @else
                            Not linked
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Created by</dt>
                    <dd class="text-sm font-medium">{{ $grn->creator?->name ?? '—' }}</dd>
                </div>
            </dl>

            @if ($grn->reversalOf)
                <flux:callout icon="arrow-uturn-left" heading="Reversal">
                    This note reverses {{ $grn->reversalOf->document_number }}.
                </flux:callout>
            @endif
        </flux:card>

        <flux:card class="space-y-3">
            <flux:heading>Posting</flux:heading>

            <dl class="space-y-2 text-sm">
                <div class="flex justify-between"><dt class="text-zinc-500 dark:text-zinc-400">Posted at</dt><dd>{{ $grn->posted_at?->format('d M Y H:i') ?? 'Not posted' }}</dd></div>
                <div class="flex justify-between"><dt class="text-zinc-500 dark:text-zinc-400">Posted by</dt><dd>{{ $grn->poster?->name ?? '—' }}</dd></div>
                <div class="flex justify-between"><dt class="text-zinc-500 dark:text-zinc-400">Transaction</dt><dd>{{ $grn->stockTransaction?->transaction_number ?? '—' }}</dd></div>
                <div class="flex justify-between"><dt class="text-zinc-500 dark:text-zinc-400">Accepted</dt><dd>{{ number_format($acceptedTotal, 4) }}</dd></div>
                <div class="flex justify-between"><dt class="text-zinc-500 dark:text-zinc-400">Rejected</dt><dd>{{ number_format($rejectedTotal, 4) }}</dd></div>
            </dl>

            <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">
                Only accepted quantities are posted. Rejected quantities remain recorded on the receipt for supplier returns.
            </flux:text>
        </flux:card>
    </div>

    <flux:card class="space-y-4">
        <div>
            <flux:heading>Receipt lines</flux:heading>
            <flux:subheading>Accepted versus rejected quantities per purchase order line.</flux:subheading>
        </div>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>Item</flux:table.column>
                <flux:table.column>Unit</flux:table.column>
                <flux:table.column align="end">Accepted</flux:table.column>
                <flux:table.column align="end">Rejected</flux:table.column>
                <flux:table.column align="end">Unit cost</flux:table.column>
                <flux:table.column>Batch</flux:table.column>
                <flux:table.column>Bin</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($grn->items as $item)
                    <flux:table.row :key="$item->id">
                        <flux:table.cell>
                            <div class="font-medium">{{ $item->item?->name }}</div>
                            <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ $item->item?->code }}</div>
                        </flux:table.cell>
                        <flux:table.cell>{{ $item->unitOfMeasure?->code }}</flux:table.cell>
                        <flux:table.cell align="end">{{ rtrim(rtrim($item->accepted_quantity, '0'), '.') }}</flux:table.cell>
                        <flux:table.cell align="end">{{ rtrim(rtrim($item->rejected_quantity, '0'), '.') }}</flux:table.cell>
                        <flux:table.cell align="end">{{ number_format((float) $item->unit_cost, 4) }}</flux:table.cell>
                        <flux:table.cell>{{ $item->batch?->batch_number ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $item->warehouseBin?->code ?? '—' }}</flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <flux:modal wire:model="showReverseModal" class="md:w-96">
        <form wire:submit="reverse" class="space-y-4">
            <div>
                <flux:heading size="lg">Reverse goods received note</flux:heading>
                <flux:subheading>An equal and opposite stock transaction is posted. Reversal fails if the stock has already been consumed.</flux:subheading>
            </div>
            <flux:textarea wire:model="reversalReason" label="Reason" rows="3" required />
            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showReverseModal', false)">Keep posting</flux:button>
                <flux:button type="submit" variant="danger">Reverse</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
