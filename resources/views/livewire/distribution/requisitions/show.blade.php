<div class="space-y-6">
    <x-page-header :title="$requisition->document_number" :description="$requisition->purpose">
        <x-slot name="actions">
            <flux:button variant="ghost" icon="arrow-left" :href="route('distribution.requisitions.index')" wire:navigate>All requisitions</flux:button>
            @can('update', $requisition)
                <flux:button icon="pencil-square" :href="route('distribution.requisitions.edit', $requisition)" wire:navigate>Edit</flux:button>
            @endcan
            @can('submit', $requisition)
                <flux:button variant="primary" icon="paper-airplane" wire:click="submit" wire:confirm="Submit this requisition for approval?">Submit</flux:button>
            @endcan
            @if ($canReserve)
                @can('reserve', $requisition)
                    <flux:button variant="primary" icon="lock-closed" wire:click="reserve" wire:confirm="Reserve available stock for the approved quantities? Shortfalls become backorders.">Reserve stock</flux:button>
                @endcan
            @endif
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
                    App\Enums\RequisitionStatus::Draft => 'zinc',
                    App\Enums\RequisitionStatus::Submitted => 'blue',
                    App\Enums\RequisitionStatus::Approved => 'green',
                    App\Enums\RequisitionStatus::PartiallyApproved => 'lime',
                    App\Enums\RequisitionStatus::Rejected => 'red',
                    App\Enums\RequisitionStatus::Returned => 'amber',
                    App\Enums\RequisitionStatus::Reserved => 'purple',
                    App\Enums\RequisitionStatus::PartiallyIssued => 'purple',
                    App\Enums\RequisitionStatus::Issued => 'green',
                    App\Enums\RequisitionStatus::Acknowledged => 'green',
                    App\Enums\RequisitionStatus::Closed => 'zinc',
                    App\Enums\RequisitionStatus::Cancelled => 'zinc',
                }">{{ $requisition->status->label() }}</flux:badge>
            </div>

            <dl class="grid gap-4 sm:grid-cols-2">
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Department</dt>
                    <dd class="text-sm font-medium">{{ $requisition->requestingDepartment?->name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Requester</dt>
                    <dd class="text-sm font-medium">{{ $requisition->requester?->name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Destination warehouse</dt>
                    <dd class="text-sm font-medium">{{ $requisition->destinationWarehouse?->name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Project</dt>
                    <dd class="text-sm font-medium">{{ $requisition->project?->name ?? 'Not project-funded' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Cost centre</dt>
                    <dd class="text-sm font-medium">{{ $requisition->costCenter?->name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Required by</dt>
                    <dd class="text-sm font-medium">{{ $requisition->required_by?->format('d M Y') ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Priority</dt>
                    <dd class="text-sm font-medium">{{ ucfirst($requisition->priority) }}</dd>
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
                    <div class="flex justify-between"><span class="text-zinc-500 dark:text-zinc-400">Status</span><flux:badge size="sm">{{ $approval->status->label() }}</flux:badge></div>
                    <div class="flex justify-between"><span class="text-zinc-500 dark:text-zinc-400">Requested</span><span>{{ $approval->requested_at?->format('d M Y H:i') ?? '—' }}</span></div>
                    <div class="flex justify-between"><span class="text-zinc-500 dark:text-zinc-400">Approved by</span><span>{{ $requisition->approvedBy?->name ?? '—' }}</span></div>
                </div>
                <flux:button class="w-full" icon="inbox" :href="route('approvals.show', $approval)" wire:navigate>Open approval</flux:button>
            @else
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">No approval request has been raised.</flux:text>
            @endif

            @can('create', App\Domain\Distribution\Models\StockIssue::class)
                @if (in_array($requisition->status, [App\Enums\RequisitionStatus::Reserved, App\Enums\RequisitionStatus::PartiallyIssued, App\Enums\RequisitionStatus::PartiallyApproved, App\Enums\RequisitionStatus::Approved], true))
                    <flux:button class="w-full" variant="primary" icon="arrow-up-tray" :href="route('distribution.issues.create', ['requisition' => $requisition->id])" wire:navigate>Issue stock</flux:button>
                @endif
            @endcan
        </flux:card>
    </div>

    <flux:card class="space-y-4">
        <div class="flex items-center justify-between">
            <div>
                <flux:heading>Lines</flux:heading>
                <flux:subheading>Approvers may reduce quantities before the requisition is approved.</flux:subheading>
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
                <flux:table.column align="end">Reserved</flux:table.column>
                <flux:table.column align="end">Issued</flux:table.column>
                <flux:table.column align="end">Outstanding</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($requisition->items as $item)
                    <flux:table.row :key="$item->id">
                        <flux:table.cell>
                            <div class="font-medium">{{ $item->item?->name }}</div>
                            <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ $item->item?->code }}@if ($item->notes) — {{ $item->notes }}@endif</div>
                        </flux:table.cell>
                        <flux:table.cell>{{ $item->unitOfMeasure?->code }}</flux:table.cell>
                        <flux:table.cell align="end">{{ rtrim(rtrim($item->requested_quantity, '0'), '.') }}</flux:table.cell>
                        <flux:table.cell align="end">
                            @can('adjustApprovedQuantities', $requisition)
                                <flux:input wire:model="approvedQuantities.{{ $item->id }}" type="number" step="0.0001" min="0" class="ml-auto w-28" />
                            @else
                                {{ rtrim(rtrim($item->approved_quantity, '0'), '.') }}
                            @endcan
                        </flux:table.cell>
                        <flux:table.cell align="end">{{ rtrim(rtrim($item->reserved_quantity, '0'), '.') }}</flux:table.cell>
                        <flux:table.cell align="end">{{ rtrim(rtrim($item->issued_quantity, '0'), '.') }}</flux:table.cell>
                        <flux:table.cell align="end">{{ rtrim(rtrim($item->outstanding_quantity, '0'), '.') }}</flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </flux:card>

    @php
        $backorders = $requisition->items->flatMap->backorders->filter(fn ($backorder) => $backorder->status === App\Enums\MaterialBackorderStatus::Open);
    @endphp

    @if ($backorders->isNotEmpty())
        <flux:card class="space-y-3">
            <div>
                <flux:heading>Open backorders</flux:heading>
                <flux:subheading>These quantities could not be covered by available stock at reservation time.</flux:subheading>
            </div>
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Item</flux:table.column>
                    <flux:table.column>Warehouse</flux:table.column>
                    <flux:table.column align="end">Outstanding</flux:table.column>
                    <flux:table.column>Expected date</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($backorders as $backorder)
                        <flux:table.row :key="$backorder->id">
                            <flux:table.cell>{{ $backorder->requisitionItem?->item?->name ?? '—' }}</flux:table.cell>
                            <flux:table.cell>{{ $backorder->warehouse?->name ?? '—' }}</flux:table.cell>
                            <flux:table.cell align="end">{{ rtrim(rtrim($backorder->outstanding_quantity, '0'), '.') }}</flux:table.cell>
                            <flux:table.cell>{{ $backorder->expected_date?->format('d M Y') ?? '—' }}</flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </flux:card>
    @endif

    <flux:card class="space-y-3">
        <flux:heading>Stock issues</flux:heading>

        @forelse ($requisition->issues as $issue)
            <div class="flex items-center justify-between rounded-lg border border-zinc-200 p-3 text-sm dark:border-zinc-700">
                <a href="{{ route('distribution.issues.show', $issue) }}" wire:navigate class="font-medium hover:underline">{{ $issue->document_number }}</a>
                <div class="flex items-center gap-3">
                    <span class="text-zinc-500 dark:text-zinc-400">{{ $issue->issue_date?->format('d M Y') }}</span>
                    <flux:badge size="sm">{{ $issue->status->label() }}</flux:badge>
                </div>
            </div>
        @empty
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">No stock has been issued against this requisition.</flux:text>
        @endforelse
    </flux:card>

    <flux:modal wire:model="showCancelModal" class="md:w-96">
        <form wire:submit="cancel" class="space-y-4">
            <div>
                <flux:heading size="lg">Cancel requisition</flux:heading>
                <flux:subheading>Open reservations and backorders are released. This cannot be undone.</flux:subheading>
            </div>
            <flux:textarea wire:model="cancellationReason" label="Reason" rows="3" required />
            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showCancelModal', false)">Keep</flux:button>
                <flux:button type="submit" variant="danger">Cancel requisition</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
