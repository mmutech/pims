<div class="space-y-6">
    <x-page-header :title="$count->document_number" :description="$count->scope_description">
        <x-slot name="actions">
            <flux:button variant="ghost" icon="arrow-left" :href="route('stocktaking.counts.index')" wire:navigate>All counts</flux:button>
            @can('update', $count)
                <flux:button icon="pencil-square" :href="route('stocktaking.counts.edit', $count)" wire:navigate>Edit</flux:button>
            @endcan
            @can('start', $count)
                <flux:button variant="primary" icon="play" wire:click="start" wire:confirm="Start this count and freeze the current on-hand snapshot?">Start count</flux:button>
            @endcan
            @can('count', $count)
                <flux:button variant="primary" icon="clipboard-document-check" wire:click="openCountModal">Record counts</flux:button>
            @endcan
            @can('adjust', $count)
                <flux:button icon="adjustments-horizontal" wire:click="createAdjustments" wire:confirm="Create one adjustment per open variance?">Create adjustments</flux:button>
            @endcan
            @can('complete', $count)
                <flux:button variant="primary" icon="check-circle" wire:click="complete" wire:confirm="Complete this stock count? A posted reconciliation is required.">Complete count</flux:button>
            @endcan
            @can('cancel', $count)
                <flux:button variant="danger" icon="x-circle" wire:click="$set('showCancelModal', true)">Cancel</flux:button>
            @endcan
            @can('delete', $count)
                <flux:button variant="danger" icon="trash" wire:click="delete" wire:confirm="Delete this draft stock count?">Delete</flux:button>
            @endcan
        </x-slot>
    </x-page-header>

    <x-domain-feedback :message="$feedback" :type="$feedbackType" />

    @if ($hideSystemQuantities)
        <flux:callout icon="eye-slash" color="blue">
            <flux:callout.heading>Blind count in progress</flux:callout.heading>
            <flux:callout.text>System quantities and variances are hidden while this blind count is open.</flux:callout.text>
        </flux:callout>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <flux:card class="space-y-4 lg:col-span-2">
            <div class="flex items-center justify-between">
                <flux:heading>Count summary</flux:heading>
                <flux:badge :color="match ($count->status) {
                    App\Enums\CountStatus::Draft => 'zinc',
                    App\Enums\CountStatus::Counting => 'blue',
                    App\Enums\CountStatus::Recount => 'purple',
                    App\Enums\CountStatus::VarianceReview => 'amber',
                    App\Enums\CountStatus::AdjustmentPending => 'orange',
                    App\Enums\CountStatus::Completed => 'green',
                    App\Enums\CountStatus::Cancelled => 'red',
                }">{{ $count->status->label() }}</flux:badge>
            </div>

            <dl class="grid gap-4 sm:grid-cols-2">
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Count type</dt>
                    <dd class="text-sm font-medium">{{ $count->count_type->label() }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Warehouse</dt>
                    <dd class="text-sm font-medium">{{ $count->warehouse?->name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Snapshot</dt>
                    <dd class="text-sm font-medium">{{ $count->snapshot_at?->format('d M Y H:i') ?? 'Not started' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Scheduled by</dt>
                    <dd class="text-sm font-medium">{{ $count->creator?->name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Tolerances</dt>
                    <dd class="text-sm font-medium">{{ rtrim(rtrim($count->tolerance_quantity, '0'), '.') }} qty / {{ number_format((float) $count->tolerance_value, 2) }} value</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Completed</dt>
                    <dd class="text-sm font-medium">{{ $count->completed_at?->format('d M Y H:i') ?? 'Not completed' }}</dd>
                </div>
                @if ($count->cancelled_at)
                    <div class="sm:col-span-2">
                        <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Cancellation</dt>
                        <dd class="text-sm font-medium">{{ $count->cancellation_reason }}</dd>
                    </div>
                @endif
            </dl>

            <div>
                <flux:heading size="sm">Scope</flux:heading>
                <div class="mt-2 flex flex-wrap gap-2">
                    @foreach ($count->scopes as $scope)
                        <flux:badge size="sm" color="zinc">
                            {{ $scope->location?->name ?? $scope->category?->name ?? $scope->item?->name ?? 'All warehouse stock' }}
                            @if ($scope->include_zero_balances)
                                · zero balances
                            @endif
                        </flux:badge>
                    @endforeach
                </div>
            </div>
        </flux:card>

        <flux:card class="space-y-4">
            <div class="flex items-center justify-between">
                <flux:heading>Reconciliation</flux:heading>
                @if ($reconciliation)
                    <flux:badge size="sm" :color="match ($reconciliation->status) {
                        App\Enums\ReconciliationStatus::Draft => 'amber',
                        App\Enums\ReconciliationStatus::Approved => 'blue',
                        App\Enums\ReconciliationStatus::Posted => 'green',
                    }">{{ $reconciliation->status->label() }}</flux:badge>
                @endif
            </div>

            @if ($reconciliation)
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-zinc-500 dark:text-zinc-400">Snapshot</dt><dd class="font-mono">{{ rtrim(rtrim($reconciliation->snapshot_quantity, '0'), '.') }}</dd></div>
                    <div class="flex justify-between"><dt class="text-zinc-500 dark:text-zinc-400">Movements</dt><dd class="font-mono">{{ rtrim(rtrim($reconciliation->movement_quantity, '0'), '.') }}</dd></div>
                    <div class="flex justify-between"><dt class="text-zinc-500 dark:text-zinc-400">Adjustments</dt><dd class="font-mono">{{ rtrim(rtrim($reconciliation->adjustment_quantity, '0'), '.') }}</dd></div>
                    <div class="flex justify-between"><dt class="text-zinc-500 dark:text-zinc-400">Ledger</dt><dd class="font-mono">{{ rtrim(rtrim($reconciliation->final_quantity, '0'), '.') }}</dd></div>
                    <div class="flex justify-between border-t border-zinc-200 pt-2 dark:border-zinc-700"><dt class="text-zinc-500 dark:text-zinc-400">Difference</dt><dd class="font-mono font-semibold">{{ rtrim(rtrim($reconciliation->difference_quantity, '0'), '.') }}</dd></div>
                </dl>

                <div class="space-y-2 text-xs text-zinc-500 dark:text-zinc-400">
                    <div>Prepared by {{ $reconciliation->preparedBy?->name ?? '—' }} on {{ $reconciliation->approved_at?->format('d M Y H:i') ?? $reconciliation->posted_at?->format('d M Y H:i') ?? '—' }}</div>
                    @if ($reconciliation->approvedBy)
                        <div>Approved by {{ $reconciliation->approvedBy->name }}</div>
                    @endif
                </div>

                <div class="flex flex-col gap-2">
                    @if ($count->snapshot_at !== null && $reconciliation->status === App\Enums\ReconciliationStatus::Draft)
                        @can('reconcile', $count)
                            <flux:button size="sm" icon="arrow-path" wire:click="prepareReconciliation" wire:confirm="Recalculate this reconciliation from the current ledger?">Recalculate</flux:button>
                        @endcan
                        @can('approve', $reconciliation)
                            <flux:button size="sm" variant="primary" icon="check" wire:click="approveReconciliation({{ $reconciliation->id }})" wire:confirm="Approve this reconciliation?">Approve</flux:button>
                        @endcan
                    @endif
                    @if ($reconciliation->status === App\Enums\ReconciliationStatus::Approved)
                        @can('post', $reconciliation)
                            <flux:button size="sm" variant="primary" icon="lock-closed" wire:click="postReconciliation({{ $reconciliation->id }})" wire:confirm="Post this reconciliation? The count can then be completed.">Post reconciliation</flux:button>
                        @endcan
                    @endif
                    @if ($reconciliation->status === App\Enums\ReconciliationStatus::Posted)
                        <flux:text class="text-xs text-green-600 dark:text-green-400">Reconciliation posted on {{ $reconciliation->posted_at?->format('d M Y H:i') }}.</flux:text>
                    @endif
                </div>
            @else
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                    No reconciliation has been prepared. The equation is snapshot + post-snapshot movements + posted adjustments = current ledger.
                </flux:text>
                @if ($count->snapshot_at !== null)
                    @can('reconcile', $count)
                        <flux:button class="w-full" size="sm" variant="primary" icon="calculator" wire:click="prepareReconciliation">Prepare reconciliation</flux:button>
                    @endcan
                @endif
            @endif
        </flux:card>
    </div>

    <flux:card class="space-y-4">
        <div>
            <flux:heading>Count lines</flux:heading>
            <flux:subheading>Expected quantities include ledger movements recorded after the snapshot, excluding this count's own adjustments.</flux:subheading>
        </div>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>Item</flux:table.column>
                <flux:table.column>Unit</flux:table.column>
                <flux:table.column>Batch</flux:table.column>
                @unless ($hideSystemQuantities)
                    <flux:table.column align="end">System</flux:table.column>
                @endunless
                <flux:table.column align="end">Counted</flux:table.column>
                @unless ($hideSystemQuantities)
                    <flux:table.column align="end">Variance</flux:table.column>
                @endunless
                <flux:table.column>Counted by</flux:table.column>
                <flux:table.column align="end">Recounts</flux:table.column>
                <flux:table.column align="end">Actions</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($count->items as $item)
                    <flux:table.row :key="$item->id">
                        <flux:table.cell>
                            <div class="font-medium">{{ $item->item?->name }}</div>
                            <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ $item->item?->code }}</div>
                        </flux:table.cell>
                        <flux:table.cell>{{ $item->item?->baseUnit?->code ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $item->batch?->batch_number ?? '—' }}</flux:table.cell>
                        @unless ($hideSystemQuantities)
                            <flux:table.cell align="end">{{ rtrim(rtrim($item->system_quantity, '0'), '.') }}</flux:table.cell>
                        @endunless
                        <flux:table.cell align="end">
                            {{ $item->counted_quantity !== null ? rtrim(rtrim($item->counted_quantity, '0'), '.') : '—' }}
                        </flux:table.cell>
                        @unless ($hideSystemQuantities)
                            <flux:table.cell align="end">
                                @if ($item->variance_quantity !== null)
                                    <span @class([
                                        'font-medium',
                                        'text-red-600 dark:text-red-400' => (float) $item->variance_quantity < 0,
                                        'text-green-600 dark:text-green-400' => (float) $item->variance_quantity > 0,
                                    ])>{{ rtrim(rtrim($item->variance_quantity, '0'), '.') }}</span>
                                @else
                                    —
                                @endif
                            </flux:table.cell>
                        @endunless
                        <flux:table.cell>
                            @if ($item->countedBy)
                                <div class="text-sm">{{ $item->countedBy->name }}</div>
                                <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ $item->counted_at?->format('d M Y H:i') }}</div>
                            @else
                                <span class="text-zinc-500 dark:text-zinc-400">Not counted</span>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell align="end">{{ $item->recounts->count() }}</flux:table.cell>
                        <flux:table.cell align="end">
                            @can('recount', $count)
                                <flux:button size="sm" variant="ghost" icon="arrow-path" aria-label="Record recount" wire:click="openRecountModal({{ $item->id }})" />
                            @endcan
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="{{ $hideSystemQuantities ? 7 : 9 }}" class="py-10 text-center text-zinc-500 dark:text-zinc-400">
                            This count has no lines yet. Start the count to capture the current snapshot.
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    @unless ($hideSystemQuantities)
        <div class="grid gap-6 lg:grid-cols-2">
            <flux:card class="space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <flux:heading>Variance analysis</flux:heading>
                        <flux:subheading>{{ $openVariances->count() }} open variances.</flux:subheading>
                    </div>
                </div>

                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>Item</flux:table.column>
                        <flux:table.column>Type</flux:table.column>
                        <flux:table.column align="end">Quantity</flux:table.column>
                        <flux:table.column align="end">Value</flux:table.column>
                        <flux:table.column>Tolerance</flux:table.column>
                        <flux:table.column>Resolution</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @forelse ($count->variances as $variance)
                            <flux:table.row :key="$variance->id">
                                <flux:table.cell>{{ $variance->stockCountItem?->item?->name ?? '—' }}</flux:table.cell>
                                <flux:table.cell>{{ $variance->variance_type->label() }}</flux:table.cell>
                                <flux:table.cell align="end">{{ rtrim(rtrim($variance->quantity, '0'), '.') }}</flux:table.cell>
                                <flux:table.cell align="end">{{ number_format((float) $variance->value, 2) }}</flux:table.cell>
                                <flux:table.cell>
                                    @if ($variance->tolerance_breached)
                                        <flux:badge size="sm" color="red">Breached</flux:badge>
                                    @else
                                        <flux:badge size="sm" color="green">Within</flux:badge>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell>
                                    <flux:badge size="sm">{{ $variance->resolution->label() }}</flux:badge>
                                    @if ($variance->adjustment)
                                        <a href="{{ route('stocktaking.adjustments.show', $variance->adjustment) }}" wire:navigate class="ml-1 text-xs hover:underline">{{ $variance->adjustment->document_number }}</a>
                                    @endif
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="6" class="py-8 text-center text-zinc-500 dark:text-zinc-400">No variances have been identified.</flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </flux:card>

            <flux:card class="space-y-4">
                <div>
                    <flux:heading>Count adjustments</flux:heading>
                    <flux:subheading>Each open variance becomes one adjustment. Posted adjustments update the ledger.</flux:subheading>
                </div>

                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>Number</flux:table.column>
                        <flux:table.column>Type</flux:table.column>
                        <flux:table.column align="end">Value</flux:table.column>
                        <flux:table.column>Status</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @forelse ($count->adjustments as $adjustment)
                            <flux:table.row :key="$adjustment->id">
                                <flux:table.cell variant="strong">
                                    <a href="{{ route('stocktaking.adjustments.show', $adjustment) }}" wire:navigate class="hover:underline">{{ $adjustment->document_number }}</a>
                                </flux:table.cell>
                                <flux:table.cell>{{ $adjustment->adjustment_type->label() }}</flux:table.cell>
                                <flux:table.cell align="end">{{ number_format((float) $adjustment->total_value, 2) }}</flux:table.cell>
                                <flux:table.cell>
                                    <flux:badge size="sm" :color="match ($adjustment->status) {
                                        App\Enums\AdjustmentStatus::Draft => 'zinc',
                                        App\Enums\AdjustmentStatus::PendingApproval => 'blue',
                                        App\Enums\AdjustmentStatus::Approved => 'amber',
                                        App\Enums\AdjustmentStatus::Posted => 'green',
                                        App\Enums\AdjustmentStatus::Reversed => 'purple',
                                        App\Enums\AdjustmentStatus::Cancelled => 'red',
                                    }">{{ $adjustment->status->label() }}</flux:badge>
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="4" class="py-8 text-center text-zinc-500 dark:text-zinc-400">No adjustments have been raised from this count.</flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </flux:card>
        </div>
    @endunless

    <flux:modal wire:model="showCountModal" class="md:w-[64rem]">
        <form wire:submit="saveCounts" class="space-y-4">
            <div>
                <flux:heading size="lg">Record counted quantities</flux:heading>
                <flux:subheading>Leave a line blank to skip it. Recording quantities moves the count into variance review.</flux:subheading>
            </div>

            <div class="max-h-[28rem] overflow-y-auto">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>Item</flux:table.column>
                        <flux:table.column>Batch</flux:table.column>
                        @unless ($hideSystemQuantities)
                            <flux:table.column align="end">System</flux:table.column>
                        @endunless
                        <flux:table.column align="end">Counted</flux:table.column>
                        <flux:table.column>Notes</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($countLines as $index => $line)
                            <flux:table.row :key="$line['stock_count_item_id']">
                                <flux:table.cell>
                                    <div class="font-medium">{{ $line['item'] }}</div>
                                    <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ $line['code'] }}</div>
                                </flux:table.cell>
                                <flux:table.cell>{{ $line['batch'] }}</flux:table.cell>
                                @unless ($hideSystemQuantities)
                                    <flux:table.cell align="end">{{ rtrim(rtrim($line['system_quantity'], '0'), '.') }}</flux:table.cell>
                                @endunless
                                <flux:table.cell align="end">
                                    <flux:input wire:model="countLines.{{ $index }}.counted_quantity" type="number" step="0.0001" min="0" class="ml-auto w-28" />
                                </flux:table.cell>
                                <flux:table.cell>
                                    <flux:input wire:model="countLines.{{ $index }}.notes" placeholder="Optional note" />
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </div>

            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showCountModal', false)">Cancel</flux:button>
                <flux:button type="submit" variant="primary">Save counted quantities</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model="showRecountModal" class="md:w-96">
        <form wire:submit="saveRecount" class="space-y-4">
            <div>
                <flux:heading size="lg">Record recount</flux:heading>
                <flux:subheading>
                    @php($recountItem = $count->items->firstWhere('id', $recountItemId))
                    {{ $recountItem?->item?->name ?? 'Count line' }}
                    @if ($recountItem?->batch)
                        · {{ $recountItem->batch->batch_number }}
                    @endif
                </flux:subheading>
            </div>
            <flux:input wire:model="recountQuantity" type="number" step="0.0001" min="0" label="Recounted quantity" required />
            <flux:textarea wire:model="recountNotes" label="Notes" rows="3" />
            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showRecountModal', false)">Cancel</flux:button>
                <flux:button type="submit" variant="primary">Save recount</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model="showCancelModal" class="md:w-96">
        <form wire:submit="cancel" class="space-y-4">
            <div>
                <flux:heading size="lg">Cancel stock count</flux:heading>
                <flux:subheading>The count will be cancelled and remain auditable.</flux:subheading>
            </div>
            <flux:textarea wire:model="cancellationReason" label="Reason" rows="3" required />
            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showCancelModal', false)">Keep count</flux:button>
                <flux:button type="submit" variant="danger">Cancel count</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
