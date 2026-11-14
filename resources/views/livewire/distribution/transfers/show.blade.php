<div class="space-y-6">
    <x-page-header :title="$transfer->document_number" :description="$transfer->reason">
        <x-slot name="actions">
            <flux:button variant="ghost" icon="arrow-left" :href="route('distribution.transfers.index')" wire:navigate>All transfers</flux:button>
            @can('update', $transfer)
                <flux:button icon="pencil-square" :href="route('distribution.transfers.edit', $transfer)" wire:navigate>Edit</flux:button>
            @endcan
            @can('submit', $transfer)
                <flux:button variant="primary" icon="paper-airplane" wire:click="submit" wire:confirm="Submit this transfer for approval?">Submit</flux:button>
            @endcan
            @can('dispatch', $transfer)
                <flux:button variant="primary" icon="truck" wire:click="openDispatchModal">Dispatch</flux:button>
            @endcan
            @can('receive', $transfer)
                <flux:button variant="primary" icon="inbox-arrow-down" wire:click="openReceiveModal">Receive</flux:button>
            @endcan
            @can('cancel', $transfer)
                <flux:button variant="danger" icon="x-circle" wire:click="$set('showCancelModal', true)">Cancel</flux:button>
            @endcan
            @can('delete', $transfer)
                <flux:button variant="danger" icon="trash" wire:click="delete" wire:confirm="Delete this draft transfer?">Delete</flux:button>
            @endcan
        </x-slot>
    </x-page-header>

    <x-domain-feedback :message="$feedback" :type="$feedbackType" />

    <div class="grid gap-6 lg:grid-cols-3">
        <flux:card class="space-y-4 lg:col-span-2">
            <div class="flex items-center justify-between">
                <flux:heading>Transfer summary</flux:heading>
                <flux:badge :color="match ($transfer->status) {
                    App\Enums\TransferStatus::Draft => 'zinc',
                    App\Enums\TransferStatus::PendingApproval => 'blue',
                    App\Enums\TransferStatus::Approved => 'green',
                    App\Enums\TransferStatus::PartiallyDispatched => 'amber',
                    App\Enums\TransferStatus::InTransit => 'purple',
                    App\Enums\TransferStatus::PartiallyReceived => 'purple',
                    App\Enums\TransferStatus::Completed => 'green',
                    App\Enums\TransferStatus::Cancelled => 'zinc',
                }">{{ $transfer->status->label() }}</flux:badge>
            </div>

            <dl class="grid gap-4 sm:grid-cols-2">
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Source warehouse</dt>
                    <dd class="text-sm font-medium">{{ $transfer->sourceWarehouse?->name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Destination warehouse</dt>
                    <dd class="text-sm font-medium">{{ $transfer->destinationWarehouse?->name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Requested by</dt>
                    <dd class="text-sm font-medium">{{ $transfer->requester?->name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Required by</dt>
                    <dd class="text-sm font-medium">{{ $transfer->required_by?->format('d M Y') ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Approved</dt>
                    <dd class="text-sm font-medium">{{ $transfer->approved_at?->format('d M Y H:i') ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Completed</dt>
                    <dd class="text-sm font-medium">{{ $transfer->completed_at?->format('d M Y H:i') ?? '—' }}</dd>
                </div>
            </dl>

            @if ($transfer->cancellation_reason)
                <flux:callout variant="danger" icon="x-circle" heading="Cancelled">
                    {{ $transfer->cancellation_reason }}
                </flux:callout>
            @endif
        </flux:card>

        <flux:card class="space-y-4">
            <flux:heading>Approval</flux:heading>

            @if ($approval)
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between"><span class="text-zinc-500 dark:text-zinc-400">Status</span><flux:badge size="sm">{{ $approval->status->label() }}</flux:badge></div>
                    <div class="flex justify-between"><span class="text-zinc-500 dark:text-zinc-400">Requested</span><span>{{ $approval->requested_at?->format('d M Y H:i') ?? '—' }}</span></div>
                </div>
                <flux:button class="w-full" icon="inbox" :href="route('approvals.show', $approval)" wire:navigate>Open approval</flux:button>
            @else
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">No approval request has been raised.</flux:text>
            @endif
        </flux:card>
    </div>

    <flux:card class="space-y-4">
        <div>
            <flux:heading>Transfer lines</flux:heading>
            <flux:subheading>Approved quantities drive dispatch; received quantities exclude damaged and missing units.</flux:subheading>
        </div>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>Item</flux:table.column>
                <flux:table.column>Unit</flux:table.column>
                <flux:table.column align="end">Requested</flux:table.column>
                <flux:table.column align="end">Approved</flux:table.column>
                <flux:table.column align="end">Dispatched</flux:table.column>
                <flux:table.column align="end">Received</flux:table.column>
                <flux:table.column align="end">Variance</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($transfer->items as $item)
                    <flux:table.row :key="$item->id">
                        <flux:table.cell>
                            <div class="font-medium">{{ $item->item?->name }}</div>
                            <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ $item->item?->code }}</div>
                        </flux:table.cell>
                        <flux:table.cell>{{ $item->unitOfMeasure?->code }}</flux:table.cell>
                        <flux:table.cell align="end">{{ rtrim(rtrim($item->requested_quantity, '0'), '.') }}</flux:table.cell>
                        <flux:table.cell align="end">{{ rtrim(rtrim($item->approved_quantity, '0'), '.') }}</flux:table.cell>
                        <flux:table.cell align="end">{{ rtrim(rtrim($item->dispatched_quantity, '0'), '.') }}</flux:table.cell>
                        <flux:table.cell align="end">{{ rtrim(rtrim($item->received_quantity, '0'), '.') }}</flux:table.cell>
                        <flux:table.cell align="end">{{ rtrim(rtrim($item->variance_quantity, '0'), '.') }}</flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <flux:card class="space-y-4">
        <div>
            <flux:heading>Dispatches</flux:heading>
            <flux:subheading>Each dispatch posts stock out of the source warehouse and into an in-transit dimension.</flux:subheading>
        </div>

        @forelse ($transfer->dispatches as $dispatch)
            <div class="space-y-2 rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
                    <div>
                        <span class="font-medium">{{ $dispatch->document_number }}</span>
                        <span class="text-zinc-500 dark:text-zinc-400"> — {{ $dispatch->dispatched_at?->format('d M Y H:i') }}</span>
                    </div>
                    <div class="text-zinc-500 dark:text-zinc-400">
                        {{ $dispatch->dispatchedBy?->name ?? '—' }}
                        @if ($dispatch->vehicle_number) · {{ $dispatch->vehicle_number }} @endif
                        @if ($dispatch->driver_name) · {{ $dispatch->driver_name }} @endif
                    </div>
                </div>
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>Item</flux:table.column>
                        <flux:table.column>Batch</flux:table.column>
                        <flux:table.column align="end">Dispatched</flux:table.column>
                        <flux:table.column align="end">Accounted</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($dispatch->items as $dispatchItem)
                            <flux:table.row :key="$dispatchItem->id">
                                <flux:table.cell>{{ $dispatchItem->item?->name ?? '—' }}</flux:table.cell>
                                <flux:table.cell>{{ $dispatchItem->batch_id ? '#'.$dispatchItem->batch_id : '—' }}</flux:table.cell>
                                <flux:table.cell align="end">{{ rtrim(rtrim($dispatchItem->quantity, '0'), '.') }}</flux:table.cell>
                                <flux:table.cell align="end">{{ rtrim(rtrim($dispatchItem->received_quantity, '0'), '.') }}</flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </div>
        @empty
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">No stock has been dispatched for this transfer yet.</flux:text>
        @endforelse
    </flux:card>

    <flux:card class="space-y-4">
        <div class="flex items-center justify-between">
            <div>
                <flux:heading>Discrepancies</flux:heading>
                <flux:subheading>Damaged and missing quantities are reconciled before the transfer can complete.</flux:subheading>
            </div>
        </div>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>Item</flux:table.column>
                <flux:table.column>Type</flux:table.column>
                <flux:table.column align="end">Quantity</flux:table.column>
                <flux:table.column>Resolution</flux:table.column>
                <flux:table.column>Notes</flux:table.column>
                <flux:table.column align="end">Actions</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($transfer->discrepancies as $discrepancy)
                    <flux:table.row :key="$discrepancy->id">
                        <flux:table.cell>{{ $discrepancy->dispatchItem?->item?->name ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $discrepancy->variance_type->label() }}</flux:table.cell>
                        <flux:table.cell align="end">{{ rtrim(rtrim($discrepancy->quantity, '0'), '.') }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$discrepancy->resolution->isPending() ? 'amber' : 'green'">{{ $discrepancy->resolution->label() }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell class="max-w-xs truncate">{{ $discrepancy->notes ?? '—' }}</flux:table.cell>
                        <flux:table.cell align="end">
                            @if ($discrepancy->resolution->isPending())
                                @can('resolveDiscrepancy', $transfer)
                                    <flux:button size="sm" icon="wrench-screwdriver" wire:click="openResolveModal({{ $discrepancy->id }})">Resolve</flux:button>
                                @endcan
                            @else
                                <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ $discrepancy->resolved_at?->format('d M Y') }}</span>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6" class="py-6 text-center text-zinc-500 dark:text-zinc-400">No discrepancies have been recorded.</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <flux:modal wire:model="showDispatchModal" class="md:w-[52rem]">
        <form wire:submit="postDispatch" class="space-y-4">
            <div>
                <flux:heading size="lg">Dispatch stock</flux:heading>
                <flux:subheading>Post stock out of the source warehouse. It remains in transit until the destination receives it.</flux:subheading>
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <flux:input wire:model="dispatchForm.vehicle_number" label="Vehicle number" />
                <flux:input wire:model="dispatchForm.driver_name" label="Driver name" />
                <flux:input wire:model="dispatchForm.dispatched_at" type="date" label="Dispatch date" />
            </div>

            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Item</flux:table.column>
                    <flux:table.column align="end">Outstanding</flux:table.column>
                    <flux:table.column>Batch</flux:table.column>
                    <flux:table.column align="end">Dispatch now</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($dispatchLines as $index => $line)
                        <flux:table.row :key="$line['stock_transfer_item_id']">
                            <flux:table.cell>
                                <div class="font-medium">{{ $line['item'] }}</div>
                                <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ $line['code'] }}</div>
                            </flux:table.cell>
                            <flux:table.cell align="end">{{ rtrim(rtrim($line['remaining'], '0'), '.') }}</flux:table.cell>
                            <flux:table.cell>
                                @if ($line['requires_batch'])
                                    <flux:select wire:model="dispatchLines.{{ $index }}.batch_id" placeholder="Select batch">
                                        @foreach ($line['batches'] as $batch)
                                            <option value="{{ $batch['id'] }}">{{ $batch['label'] }}</option>
                                        @endforeach
                                    </flux:select>
                                @else
                                    <span class="text-zinc-500 dark:text-zinc-400">Not tracked</span>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell align="end">
                                <flux:input wire:model="dispatchLines.{{ $index }}.quantity" type="number" step="0.0001" min="0" class="ml-auto w-32" />
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>

            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showDispatchModal', false)">Cancel</flux:button>
                <flux:button type="submit" variant="primary">Post dispatch</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model="showReceiveModal" class="md:w-[52rem]">
        <form wire:submit="receive" class="space-y-4">
            <div>
                <flux:heading size="lg">Receive transfer</flux:heading>
                <flux:subheading>Good quantities enter available stock, damaged quantities are quarantined, and missing quantities remain in transit.</flux:subheading>
            </div>

            <flux:select wire:model.live="receiveDispatchId" label="Dispatch">
                @foreach ($outstandingDispatches as $dispatch)
                    <option value="{{ $dispatch->id }}">{{ $dispatch->document_number }} — {{ $dispatch->dispatched_at?->format('d M Y') }}</option>
                @endforeach
            </flux:select>

            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Item</flux:table.column>
                    <flux:table.column align="end">Outstanding</flux:table.column>
                    <flux:table.column align="end">Good</flux:table.column>
                    <flux:table.column align="end">Damaged</flux:table.column>
                    <flux:table.column align="end">Missing</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($receiveLines as $index => $line)
                        <flux:table.row :key="$line['dispatch_item_id']">
                            <flux:table.cell>
                                <div class="font-medium">{{ $line['item'] }}</div>
                                <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ $line['code'] }}</div>
                            </flux:table.cell>
                            <flux:table.cell align="end">{{ rtrim(rtrim($line['remaining'], '0'), '.') }}</flux:table.cell>
                            <flux:table.cell align="end">
                                <flux:input wire:model="receiveLines.{{ $index }}.received_quantity" type="number" step="0.0001" min="0" class="ml-auto w-24" />
                            </flux:table.cell>
                            <flux:table.cell align="end">
                                <flux:input wire:model="receiveLines.{{ $index }}.damaged_quantity" type="number" step="0.0001" min="0" class="ml-auto w-24" />
                            </flux:table.cell>
                            <flux:table.cell align="end">
                                <flux:input wire:model="receiveLines.{{ $index }}.missing_quantity" type="number" step="0.0001" min="0" class="ml-auto w-24" />
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>

            <flux:textarea wire:model="receiveNotes" label="Notes" rows="2" />

            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showReceiveModal', false)">Cancel</flux:button>
                <flux:button type="submit" variant="primary">Post receipt</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model="showResolveModal" class="md:w-96">
        <form wire:submit="resolve" class="space-y-4">
            <div>
                <flux:heading size="lg">Resolve discrepancy</flux:heading>
                <flux:subheading>Choose the terminal outcome for this shortage or damage record.</flux:subheading>
            </div>

            <flux:select wire:model="discrepancyResolution" label="Resolution">
                <option value="write_off">Write off</option>
                <option value="return_to_source">Return to source warehouse</option>
                <option value="claim">Supplier/carrier claim</option>
                <option value="replacement">Replacement</option>
            </flux:select>

            <flux:textarea wire:model="discrepancyNotes" label="Notes" rows="3" />

            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showResolveModal', false)">Cancel</flux:button>
                <flux:button type="submit" variant="primary">Resolve</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model="showCancelModal" class="md:w-96">
        <form wire:submit="cancel" class="space-y-4">
            <div>
                <flux:heading size="lg">Cancel transfer</flux:heading>
                <flux:subheading>The transfer will be cancelled and remain auditable.</flux:subheading>
            </div>
            <flux:textarea wire:model="cancellationReason" label="Reason" rows="3" required />
            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showCancelModal', false)">Keep transfer</flux:button>
                <flux:button type="submit" variant="danger">Cancel transfer</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
