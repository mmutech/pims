<div class="space-y-6">
    <x-page-header :title="$adjustment->document_number" :description="$adjustment->reason">
        <x-slot name="actions">
            <flux:button variant="ghost" icon="arrow-left" :href="route('stocktaking.adjustments.index')" wire:navigate>All adjustments</flux:button>
            @can('update', $adjustment)
                <flux:button icon="pencil-square" :href="route('stocktaking.adjustments.edit', $adjustment)" wire:navigate>Edit</flux:button>
            @endcan
            @can('submit', $adjustment)
                <flux:button variant="primary" icon="paper-airplane" wire:click="submit" wire:confirm="Submit this adjustment for approval?">Submit</flux:button>
            @endcan
            @can('post', $adjustment)
                <flux:button variant="primary" icon="arrow-down-tray" wire:click="post" wire:confirm="Post this adjustment? On-hand stock will change immediately.">Post</flux:button>
            @endcan
            @can('reverse', $adjustment)
                <flux:button icon="arrow-uturn-left" wire:click="$set('showReverseModal', true)">Reverse</flux:button>
            @endcan
            @can('cancel', $adjustment)
                <flux:button variant="danger" icon="x-circle" wire:click="$set('showCancelModal', true)">Cancel</flux:button>
            @endcan
            @can('delete', $adjustment)
                <flux:button variant="danger" icon="trash" wire:click="delete" wire:confirm="Delete this draft stock adjustment?">Delete</flux:button>
            @endcan
        </x-slot>
    </x-page-header>

    <x-domain-feedback :message="$feedback" :type="$feedbackType" />

    <div class="grid gap-6 lg:grid-cols-3">
        <flux:card class="space-y-4 lg:col-span-2">
            <div class="flex items-center justify-between">
                <flux:heading>Adjustment summary</flux:heading>
                <flux:badge :color="match ($adjustment->status) {
                    App\Enums\AdjustmentStatus::Draft => 'zinc',
                    App\Enums\AdjustmentStatus::PendingApproval => 'blue',
                    App\Enums\AdjustmentStatus::Approved => 'amber',
                    App\Enums\AdjustmentStatus::Posted => 'green',
                    App\Enums\AdjustmentStatus::Reversed => 'purple',
                    App\Enums\AdjustmentStatus::Cancelled => 'red',
                }">{{ $adjustment->status->label() }}</flux:badge>
            </div>

            <dl class="grid gap-4 sm:grid-cols-2">
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Type</dt>
                    <dd class="text-sm font-medium">{{ $adjustment->adjustment_type->label() }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Reason code</dt>
                    <dd class="text-sm font-medium">{{ $adjustment->reason_code->label() }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Warehouse</dt>
                    <dd class="text-sm font-medium">{{ $adjustment->warehouse?->name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Total value</dt>
                    <dd class="text-sm font-medium">{{ number_format((float) $adjustment->total_value, 2) }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Stock count</dt>
                    <dd class="text-sm font-medium">
                        @if ($adjustment->stockCount)
                            <a href="{{ route('stocktaking.counts.show', $adjustment->stockCount) }}" wire:navigate class="hover:underline">{{ $adjustment->stockCount->document_number }}</a>
                        @else
                            —
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Created by</dt>
                    <dd class="text-sm font-medium">{{ $adjustment->creator?->name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Approved</dt>
                    <dd class="text-sm font-medium">
                        {{ $adjustment->approved_at?->format('d M Y H:i') ?? 'Not approved' }}
                        @if ($adjustment->approvedBy)
                            · {{ $adjustment->approvedBy->name }}
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Posted</dt>
                    <dd class="text-sm font-medium">
                        {{ $adjustment->posted_at?->format('d M Y H:i') ?? 'Not posted' }}
                        @if ($adjustment->postedBy)
                            · {{ $adjustment->postedBy->name }}
                        @endif
                    </dd>
                </div>
                @if ($adjustment->cancelled_at)
                    <div class="sm:col-span-2">
                        <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Cancellation</dt>
                        <dd class="text-sm font-medium">{{ $adjustment->cancellation_reason }}</dd>
                    </div>
                @endif
                @if ($adjustment->reversalOf)
                    <div class="sm:col-span-2">
                        <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Reversal of</dt>
                        <dd class="text-sm font-medium">
                            <a href="{{ route('stocktaking.adjustments.show', $adjustment->reversalOf) }}" wire:navigate class="hover:underline">{{ $adjustment->reversalOf->document_number }}</a>
                        </dd>
                    </div>
                @endif
            </dl>
        </flux:card>

        <div class="space-y-6">
            <flux:card class="space-y-3">
                <flux:heading>Approval</flux:heading>
                @if ($approval)
                    <div class="flex justify-between"><span class="text-zinc-500 dark:text-zinc-400">Status</span><flux:badge size="sm">{{ $approval->status->label() }}</flux:badge></div>
                    <div class="flex justify-between"><span class="text-zinc-500 dark:text-zinc-400">Requested</span><span>{{ $approval->requested_at?->format('d M Y H:i') ?? '—' }}</span></div>
                    <flux:button class="w-full" icon="inbox" :href="route('approvals.show', $approval)" wire:navigate>Open approval</flux:button>
                @else
                    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                        {{ $adjustment->requiresApproval()
                            ? 'Approval is required before this adjustment can be posted. Submit it to start the workflow.'
                            : 'This adjustment is below the approval threshold and can be posted directly.' }}
                    </flux:text>
                @endif
            </flux:card>

            <flux:card class="space-y-3">
                <flux:heading>Segregation of duties</flux:heading>
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                    The creator cannot post their own adjustment, and the original poster cannot reverse it.
                    Reversals create a new equal and opposite document; posted rows are never edited.
                </flux:text>
            </flux:card>
        </div>
    </div>

    <flux:card class="space-y-4">
        <div>
            <flux:heading>Adjustment lines</flux:heading>
            <flux:subheading>Signed quantities are posted through the centralized inventory posting service.</flux:subheading>
        </div>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>Item</flux:table.column>
                <flux:table.column>Unit</flux:table.column>
                <flux:table.column>Batch</flux:table.column>
                <flux:table.column align="end">Quantity</flux:table.column>
                <flux:table.column align="end">Unit cost</flux:table.column>
                <flux:table.column align="end">Value</flux:table.column>
                <flux:table.column>Note</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($adjustment->items as $item)
                    <flux:table.row :key="$item->id">
                        <flux:table.cell>
                            <div class="font-medium">{{ $item->item?->name }}</div>
                            <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ $item->item?->code }}</div>
                        </flux:table.cell>
                        <flux:table.cell>{{ $item->unitOfMeasure?->code }}</flux:table.cell>
                        <flux:table.cell>{{ $item->batch?->batch_number ?? '—' }}</flux:table.cell>
                        <flux:table.cell align="end">{{ rtrim(rtrim($item->quantity_delta, '0'), '.') }}</flux:table.cell>
                        <flux:table.cell align="end">{{ number_format((float) $item->unit_cost, 6) }}</flux:table.cell>
                        <flux:table.cell align="end">{{ number_format((float) $item->value_delta, 2) }}</flux:table.cell>
                        <flux:table.cell>{{ $item->reason ?? '—' }}</flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>

        @if ($adjustment->reversals->isNotEmpty())
            <div class="space-y-2">
                <flux:heading size="sm">Reversal documents</flux:heading>
                @foreach ($adjustment->reversals as $reversal)
                    <a href="{{ route('stocktaking.adjustments.show', $reversal) }}" wire:navigate class="flex items-center justify-between rounded-lg border border-zinc-200 px-3 py-2 text-sm hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800">
                        <span class="font-medium">{{ $reversal->document_number }}</span>
                        <flux:badge size="sm">{{ $reversal->status->label() }}</flux:badge>
                    </a>
                @endforeach
            </div>
        @endif
    </flux:card>

    <flux:modal wire:model="showReverseModal" class="md:w-96">
        <form wire:submit="reverse" class="space-y-4">
            <div>
                <flux:heading size="lg">Reverse stock adjustment</flux:heading>
                <flux:subheading>An equal and opposite posted document will be created.</flux:subheading>
            </div>
            <flux:textarea wire:model="reversalReason" label="Reversal reason" rows="3" required />
            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showReverseModal', false)">Keep adjustment</flux:button>
                <flux:button type="submit" variant="danger">Reverse</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model="showCancelModal" class="md:w-96">
        <form wire:submit="cancel" class="space-y-4">
            <div>
                <flux:heading size="lg">Cancel stock adjustment</flux:heading>
                <flux:subheading>The document will be cancelled and remain auditable.</flux:subheading>
            </div>
            <flux:textarea wire:model="cancellationReason" label="Reason" rows="3" required />
            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showCancelModal', false)">Keep adjustment</flux:button>
                <flux:button type="submit" variant="danger">Cancel adjustment</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
