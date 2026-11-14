<div class="space-y-6">
    <x-page-header :title="$requisition->document_number" :description="$requisition->justification">
        <x-slot name="actions">
            <flux:button variant="ghost" icon="arrow-left" :href="route('procurement.requisitions.index')" wire:navigate>All requisitions</flux:button>
            @can('update', $requisition)
                <flux:button icon="pencil-square" :href="route('procurement.requisitions.edit', $requisition)" wire:navigate>Edit</flux:button>
            @endcan
            @can('submit', $requisition)
                <flux:button variant="primary" icon="paper-airplane" wire:click="submit" wire:confirm="Submit this requisition for approval?">Submit</flux:button>
            @endcan
            @can('cancel', $requisition)
                <flux:button variant="danger" icon="x-circle" wire:click="$set('showCancelModal', true)">Cancel</flux:button>
            @endcan
        </x-slot>
    </x-page-header>

    <x-domain-feedback :message="$feedback" :type="$feedbackType" />

    <div class="grid gap-6 lg:grid-cols-3">
        <flux:card class="space-y-4 lg:col-span-2">
            <div class="flex items-center justify-between">
                <flux:heading>Requisition summary</flux:heading>
                <flux:badge :color="match ($requisition->status) {
                    App\Enums\PurchaseRequisitionStatus::Draft => 'zinc',
                    App\Enums\PurchaseRequisitionStatus::Submitted => 'blue',
                    App\Enums\PurchaseRequisitionStatus::Approved => 'green',
                    App\Enums\PurchaseRequisitionStatus::PartiallyApproved => 'lime',
                    App\Enums\PurchaseRequisitionStatus::Rejected => 'red',
                    App\Enums\PurchaseRequisitionStatus::Returned => 'amber',
                    App\Enums\PurchaseRequisitionStatus::Converted => 'purple',
                    App\Enums\PurchaseRequisitionStatus::Cancelled => 'zinc',
                }">{{ $requisition->status->label() }}</flux:badge>
            </div>

            <dl class="grid gap-4 sm:grid-cols-2">
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Department</dt>
                    <dd class="text-sm font-medium">{{ $requisition->requestingDepartment?->name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Business unit</dt>
                    <dd class="text-sm font-medium">{{ $requisition->businessUnit?->name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Requester</dt>
                    <dd class="text-sm font-medium">{{ $requisition->requester?->name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Required by</dt>
                    <dd class="text-sm font-medium">{{ $requisition->required_by?->format('d M Y') ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Estimated total</dt>
                    <dd class="text-sm font-medium">{{ $requisition->currency }} {{ number_format((float) $requisition->total_estimated_amount, 2) }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Approved amount</dt>
                    <dd class="text-sm font-medium">{{ $requisition->currency }} {{ number_format((float) $requisition->approved_amount, 2) }}</dd>
                </div>
            </dl>

            @if ($requisition->cancellation_reason)
                <flux:callout variant="danger" icon="x-circle" heading="Cancelled">
                    {{ $requisition->cancellation_reason }}
                </flux:callout>
            @endif
        </flux:card>

        <flux:card class="space-y-4">
            <flux:heading>Approval</flux:heading>
            @if ($approval)
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between"><span class="text-zinc-500">Status</span><flux:badge size="sm">{{ $approval->status->label() }}</flux:badge></div>
                    <div class="flex justify-between"><span class="text-zinc-500">Requested</span><span>{{ $approval->requested_at?->format('d M Y H:i') }}</span></div>
                </div>
                <flux:button class="w-full" icon="inbox" :href="route('approvals.show', $approval)" wire:navigate>Open approval</flux:button>
            @else
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">No approval request has been raised.</flux:text>
            @endif

            @can('create', App\Domain\Procurement\Models\RequestForQuotation::class)
                @if ($requisition->isApprovedForSourcing())
                    <flux:button class="w-full" variant="primary" icon="document-magnifying-glass" wire:click="$set('showRfqModal', true)">Create RFQ</flux:button>
                @endif
            @endcan
        </flux:card>
    </div>

    <flux:card class="space-y-4">
        <div class="flex items-center justify-between">
            <div>
                <flux:heading>Lines</flux:heading>
                <flux:subheading>Approved quantities may be reduced before the requisition is finalised.</flux:subheading>
            </div>
            @can('adjustApprovedQuantities', $requisition)
                <flux:button size="sm" icon="check" wire:click="saveApprovedQuantities">Save approved quantities</flux:button>
            @endcan
        </div>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>Item</flux:table.column>
                <flux:table.column>Unit</flux:table.column>
                <flux:table.column align="end">Requested</flux:table.column>
                <flux:table.column align="end">Approved</flux:table.column>
                <flux:table.column align="end">Sourced</flux:table.column>
                <flux:table.column align="end">Unit price</flux:table.column>
                <flux:table.column align="end">Line total</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($requisition->items as $item)
                    <flux:table.row :key="$item->id">
                        <flux:table.cell>
                            <div class="font-medium">{{ $item->item?->name }}</div>
                            <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ $item->item?->code }}@if ($item->specification) · {{ $item->specification }}@endif</div>
                        </flux:table.cell>
                        <flux:table.cell>{{ $item->unitOfMeasure?->code }}</flux:table.cell>
                        <flux:table.cell align="end">{{ rtrim(rtrim($item->requested_quantity, '0'), '.') }}</flux:table.cell>
                        <flux:table.cell align="end">
                            @can('adjustApprovedQuantities', $requisition)
                                @if ($requisition->canBeEdited() || $requisition->status === App\Enums\PurchaseRequisitionStatus::Submitted)
                                    <flux:input wire:model="approvedQuantities.{{ $item->id }}" type="number" step="0.0001" min="0" class="ml-auto w-28" />
                                @else
                                    {{ rtrim(rtrim($item->approved_quantity, '0'), '.') }}
                                @endif
                            @else
                                {{ rtrim(rtrim($item->approved_quantity, '0'), '.') }}
                            @endcan
                        </flux:table.cell>
                        <flux:table.cell align="end">{{ rtrim(rtrim($item->converted_quantity, '0'), '.') }}</flux:table.cell>
                        <flux:table.cell align="end">{{ number_format((float) $item->estimated_unit_price, 2) }}</flux:table.cell>
                        <flux:table.cell align="end">{{ number_format((float) $item->estimated_line_total, 2) }}</flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <div class="grid gap-6 lg:grid-cols-2">
        <flux:card class="space-y-3">
            <flux:heading>Requests for quotation</flux:heading>
            @forelse ($rfqs as $rfq)
                <div class="flex items-center justify-between rounded-lg border border-zinc-200 p-3 text-sm dark:border-zinc-700">
                    <a href="{{ route('procurement.rfqs.show', $rfq) }}" wire:navigate class="font-medium hover:underline">{{ $rfq->document_number }}</a>
                    <flux:badge size="sm">{{ $rfq->status->label() }}</flux:badge>
                </div>
            @empty
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">No RFQs raised from this requisition.</flux:text>
            @endforelse
        </flux:card>

        <flux:card class="space-y-3">
            <flux:heading>Purchase orders</flux:heading>
            @forelse ($orders as $order)
                <div class="flex items-center justify-between rounded-lg border border-zinc-200 p-3 text-sm dark:border-zinc-700">
                    <a href="{{ route('procurement.orders.show', $order) }}" wire:navigate class="font-medium hover:underline">{{ $order->document_number }}</a>
                    <flux:badge size="sm">{{ $order->status->label() }}</flux:badge>
                </div>
            @empty
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">No purchase orders reference this requisition.</flux:text>
            @endforelse
        </flux:card>
    </div>

    <flux:modal wire:model="showCancelModal" class="md:w-96">
        <form wire:submit="cancel" class="space-y-4">
            <div>
                <flux:heading size="lg">Cancel requisition</flux:heading>
                <flux:subheading>Linked RFQs are cancelled. This cannot be undone.</flux:subheading>
            </div>
            <flux:textarea wire:model="cancellationReason" label="Reason" rows="3" required />
            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showCancelModal', false)">Keep</flux:button>
                <flux:button type="submit" variant="danger">Cancel requisition</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model="showRfqModal" class="md:w-[40rem]">
        <form wire:submit="createRfq" class="space-y-4">
            <div>
                <flux:heading size="lg">Create request for quotation</flux:heading>
                <flux:subheading>All remaining approved quantities are carried into the RFQ.</flux:subheading>
            </div>

            <flux:input wire:model="rfqForm.title" label="Title" required />
            <flux:textarea wire:model="rfqForm.description" label="Description" rows="2" />
            <flux:input wire:model="rfqForm.closing_date" type="date" label="Closing date" required />

            <div>
                <flux:label>Invited suppliers</flux:label>
                <div class="mt-2 grid gap-2 sm:grid-cols-2">
                    @foreach ($suppliers as $supplier)
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" value="{{ $supplier->id }}" wire:model="rfqForm.supplier_ids" class="rounded border-zinc-300">
                            {{ $supplier->name }}
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showRfqModal', false)">Cancel</flux:button>
                <flux:button type="submit" variant="primary">Create RFQ</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
