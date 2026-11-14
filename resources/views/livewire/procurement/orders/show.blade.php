<div class="space-y-6">
    <x-page-header :title="$order->document_number" :description="$order->supplier?->name ?? 'Purchase order'">
        <x-slot name="actions">
            <flux:button variant="ghost" icon="arrow-left" :href="route('procurement.orders.index')" wire:navigate>All purchase orders</flux:button>
            @can('update', $order)
                @if ($order->canBeEdited())
                    <flux:button icon="pencil-square" :href="route('procurement.orders.edit', $order)" wire:navigate>Edit</flux:button>
                @endif
            @endcan
            @can('submit', $order)
                <flux:button variant="primary" icon="paper-airplane" wire:click="submit" wire:confirm="Submit this purchase order for approval?">Submit</flux:button>
            @endcan
            @can('amend', $order)
                <flux:button icon="document-plus" wire:click="openAmendmentModal">Amend</flux:button>
            @endcan
            @can('close', $order)
                @if ($order->status !== App\Enums\PurchaseOrderStatus::Closed)
                    <flux:button icon="lock-closed" wire:click="close" wire:confirm="Close this purchase order for further deliveries?">Close</flux:button>
                @endif
            @endcan
            @can('cancel', $order)
                <flux:button variant="danger" icon="x-circle" wire:click="$set('showCancelModal', true)">Cancel</flux:button>
            @endcan
        </x-slot>
    </x-page-header>

    <x-domain-feedback :message="$feedback" :type="$feedbackType" />

    <div class="grid gap-6 lg:grid-cols-3">
        <flux:card class="space-y-4 lg:col-span-2">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <flux:heading>Order summary</flux:heading>
                    <flux:badge size="sm" variant="outline">v{{ $order->version }}</flux:badge>
                </div>
                <flux:badge :color="match ($order->status) {
                    App\Enums\PurchaseOrderStatus::Draft => 'zinc',
                    App\Enums\PurchaseOrderStatus::PendingApproval => 'blue',
                    App\Enums\PurchaseOrderStatus::Approved => 'green',
                    App\Enums\PurchaseOrderStatus::PartiallyReceived => 'lime',
                    App\Enums\PurchaseOrderStatus::FullyReceived => 'teal',
                    App\Enums\PurchaseOrderStatus::Closed => 'purple',
                    App\Enums\PurchaseOrderStatus::Cancelled => 'red',
                }">{{ $order->status->label() }}</flux:badge>
            </div>

            <dl class="grid gap-4 sm:grid-cols-2">
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Supplier</dt>
                    <dd class="text-sm font-medium">{{ $order->supplier?->name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Delivery warehouse</dt>
                    <dd class="text-sm font-medium">{{ $order->deliveryWarehouse?->name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Expected delivery</dt>
                    <dd class="text-sm font-medium">{{ $order->expected_delivery_date?->format('d M Y') ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Payment terms</dt>
                    <dd class="text-sm font-medium">{{ $order->payment_terms ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Incoterms</dt>
                    <dd class="text-sm font-medium">{{ $order->incoterms ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Requisition</dt>
                    <dd class="text-sm font-medium">
                        @if ($order->purchaseRequisition)
                            <a href="{{ route('procurement.requisitions.show', $order->purchaseRequisition) }}" wire:navigate class="hover:underline">{{ $order->purchaseRequisition->document_number }}</a>
                        @else
                            Not linked
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Accepted quotation</dt>
                    <dd class="text-sm font-medium">
                        @if ($order->supplierQuotation)
                            <a href="{{ route('procurement.quotations.show', $order->supplierQuotation) }}" wire:navigate class="hover:underline">{{ $order->supplierQuotation->document_number }}</a>
                        @else
                            Not linked
                        @endif
                    </dd>
                </div>
            </dl>

            @if ($order->cancellation_reason)
                <flux:callout variant="danger" icon="x-circle" heading="Cancelled">
                    {{ $order->cancellation_reason }}
                </flux:callout>
            @endif
        </flux:card>

        <flux:card class="space-y-4">
            <div class="flex items-center justify-between">
                <flux:heading>Approval</flux:heading>
                @if ($order->approved_at)
                    <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ $order->approved_at->format('d M Y') }}</span>
                @endif
            </div>
            @if ($approval)
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between"><span class="text-zinc-500 dark:text-zinc-400">Status</span><flux:badge size="sm">{{ $approval->status->label() }}</flux:badge></div>
                    <div class="flex justify-between"><span class="text-zinc-500 dark:text-zinc-400">Requested</span><span>{{ $approval->requested_at?->format('d M Y H:i') }}</span></div>
                </div>
                <flux:button class="w-full" icon="inbox" :href="route('approvals.show', $approval)" wire:navigate>Open approval</flux:button>
            @else
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">No approval request has been raised.</flux:text>
            @endif

            <dl class="space-y-2 border-t border-zinc-200 pt-3 text-sm dark:border-zinc-700">
                <div class="flex justify-between"><dt class="text-zinc-500 dark:text-zinc-400">Subtotal</dt><dd>{{ $order->currency }} {{ number_format((float) $order->subtotal, 2) }}</dd></div>
                <div class="flex justify-between"><dt class="text-zinc-500 dark:text-zinc-400">Tax</dt><dd>{{ $order->currency }} {{ number_format((float) $order->tax_amount, 2) }}</dd></div>
                <div class="flex justify-between pt-1 text-base font-semibold"><dt>Total</dt><dd>{{ $order->currency }} {{ number_format((float) $order->total_amount, 2) }}</dd></div>
            </dl>
        </flux:card>
    </div>

    <flux:card class="space-y-4">
        <div>
            <flux:heading>Order lines</flux:heading>
            <flux:subheading>Received quantities will be updated by goods receipts in a later phase.</flux:subheading>
        </div>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>Item</flux:table.column>
                <flux:table.column>Unit</flux:table.column>
                <flux:table.column align="end">Ordered</flux:table.column>
                <flux:table.column align="end">Received</flux:table.column>
                <flux:table.column align="end">Outstanding</flux:table.column>
                <flux:table.column align="end">Unit price</flux:table.column>
                <flux:table.column align="end">Tax %</flux:table.column>
                <flux:table.column align="end">Line total</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($order->items as $item)
                    <flux:table.row :key="$item->id">
                        <flux:table.cell>
                            <div class="font-medium">{{ $item->item?->name }}</div>
                            <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ $item->item?->code }}</div>
                        </flux:table.cell>
                        <flux:table.cell>{{ $item->unitOfMeasure?->code }}</flux:table.cell>
                        <flux:table.cell align="end">{{ rtrim(rtrim($item->ordered_quantity, '0'), '.') }}</flux:table.cell>
                        <flux:table.cell align="end">{{ rtrim(rtrim($item->received_quantity, '0'), '.') }}</flux:table.cell>
                        <flux:table.cell align="end">{{ rtrim(rtrim($item->remainingQuantity(), '0'), '.') }}</flux:table.cell>
                        <flux:table.cell align="end">{{ number_format((float) $item->unit_price, 4) }}</flux:table.cell>
                        <flux:table.cell align="end">{{ rtrim(rtrim($item->tax_rate, '0'), '.') }}</flux:table.cell>
                        <flux:table.cell align="end">{{ number_format((float) $item->line_total, 2) }}</flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <flux:card class="space-y-3">
        <div>
            <flux:heading>Amendments</flux:heading>
            <flux:subheading>Every approved amendment bumps the order version and preserves the original values.</flux:subheading>
        </div>

        @forelse ($amendments as $amendment)
            <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <div class="text-sm font-semibold">Amendment #{{ $amendment->amendment_number }}</div>
                        <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ $amendment->reason }}</div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <flux:badge size="sm" :color="match ($amendment->status) {
                            App\Enums\PurchaseOrderAmendmentStatus::Approved => 'green',
                            App\Enums\PurchaseOrderAmendmentStatus::PendingApproval => 'blue',
                            App\Enums\PurchaseOrderAmendmentStatus::Rejected => 'red',
                            App\Enums\PurchaseOrderAmendmentStatus::Returned => 'amber',
                            default => 'zinc',
                        }">{{ $amendment->status->label() }}</flux:badge>

                        @php($amendmentApproval = $amendmentApprovals[$amendment->id] ?? null)

                        @if ($amendmentApproval)
                            <flux:button
                                size="sm"
                                variant="ghost"
                                icon="inbox"
                                :href="route('approvals.show', $amendmentApproval)"
                                wire:navigate
                            >Open approval</flux:button>
                        @endif
                    </div>
                </div>

                <flux:table class="mt-3">
                    <flux:table.columns>
                        <flux:table.column>Line</flux:table.column>
                        <flux:table.column align="end">Original qty</flux:table.column>
                        <flux:table.column align="end">Amended qty</flux:table.column>
                        <flux:table.column align="end">Original price</flux:table.column>
                        <flux:table.column align="end">Amended price</flux:table.column>
                        <flux:table.column align="end">Amended total</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($amendment->items as $line)
                            <flux:table.row :key="$line->id">
                                <flux:table.cell>{{ $line->purchaseOrderItem?->item?->name ?? 'Line #'.$line->purchase_order_item_id }}</flux:table.cell>
                                <flux:table.cell align="end">{{ rtrim(rtrim($line->original_quantity, '0'), '.') }}</flux:table.cell>
                                <flux:table.cell align="end">{{ rtrim(rtrim($line->amended_quantity, '0'), '.') }}</flux:table.cell>
                                <flux:table.cell align="end">{{ number_format((float) $line->original_unit_price, 4) }}</flux:table.cell>
                                <flux:table.cell align="end">{{ number_format((float) $line->amended_unit_price, 4) }}</flux:table.cell>
                                <flux:table.cell align="end">{{ number_format((float) $line->amended_line_total, 2) }}</flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </div>
        @empty
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">No amendments have been raised for this order.</flux:text>
        @endforelse
    </flux:card>

    <flux:modal wire:model="showCancelModal" class="md:w-96">
        <form wire:submit="cancel" class="space-y-4">
            <div>
                <flux:heading size="lg">Cancel purchase order</flux:heading>
                <flux:subheading>The supplier commitment ends. The history is retained.</flux:subheading>
            </div>
            <flux:textarea wire:model="cancellationReason" label="Reason" rows="3" required />
            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showCancelModal', false)">Keep order</flux:button>
                <flux:button type="submit" variant="danger">Cancel order</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model="showAmendmentModal" class="md:w-[44rem]">
        <form wire:submit="createAmendment" class="space-y-4">
            <div>
                <flux:heading size="lg">Amend purchase order</flux:heading>
                <flux:subheading>Quantities cannot fall below quantities already received. Changes apply once approved.</flux:subheading>
            </div>

            <flux:textarea wire:model="amendmentForm.reason" label="Reason for amendment" rows="2" required />

            <div class="space-y-3">
                @foreach ($amendmentForm['items'] ?? [] as $index => $line)
                    @php($orderLine = $order->items->firstWhere('id', (int) $line['purchase_order_item_id']))
                    <div class="grid items-end gap-3 rounded-lg border border-zinc-200 p-3 sm:grid-cols-12 dark:border-zinc-700">
                        <div class="sm:col-span-4">
                            <flux:label>Line</flux:label>
                            <div class="mt-2 text-sm font-medium">{{ $orderLine?->item?->name ?? 'Unknown line' }}</div>
                            <div class="text-xs text-zinc-500 dark:text-zinc-400">Current: {{ rtrim(rtrim($orderLine?->ordered_quantity ?? '0', '0'), '.') }} @ {{ number_format((float) ($orderLine?->unit_price ?? 0), 4) }}</div>
                        </div>
                        <flux:input wire:model="amendmentForm.items.{{ $index }}.amended_quantity" type="number" step="0.0001" min="0" label="New quantity" class="sm:col-span-4" required />
                        <flux:input wire:model="amendmentForm.items.{{ $index }}.amended_unit_price" type="number" step="0.0001" min="0" label="New unit price" class="sm:col-span-4" required />
                    </div>
                @endforeach
            </div>

            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" wire:model="amendmentForm.submit_now" class="rounded border-zinc-300">
                Submit for approval immediately
            </label>

            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showAmendmentModal', false)">Cancel</flux:button>
                <flux:button type="submit" variant="primary">Save amendment</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
