<div class="space-y-6">
    <x-page-header :title="$rfq->document_number" :description="$rfq->title">
        <x-slot name="actions">
            <flux:button variant="ghost" icon="arrow-left" :href="route('procurement.rfqs.index')" wire:navigate>All RFQs</flux:button>
            @can('update', $rfq)
                @if ($rfq->canBeEdited())
                    <flux:button icon="pencil-square" :href="route('procurement.rfqs.edit', $rfq)" wire:navigate>Edit</flux:button>
                @endif
            @endcan
            @can('issue', $rfq)
                <flux:button variant="primary" icon="paper-airplane" wire:click="issue" wire:confirm="Issue this RFQ to the invited suppliers?">Issue RFQ</flux:button>
            @endcan
            @if ($rfq->status === App\Enums\RequestForQuotationStatus::Issued)
                @can('update', $rfq)
                    <flux:button icon="lock-closed" wire:click="close" wire:confirm="Close this RFQ to new quotations?">Close</flux:button>
                @endcan
            @endif
            @can('cancel', $rfq)
                <flux:button variant="danger" icon="x-circle" wire:click="$set('showCancelModal', true)">Cancel</flux:button>
            @endcan
        </x-slot>
    </x-page-header>

    <x-domain-feedback :message="$feedback" :type="$feedbackType" />

    <div class="grid gap-6 lg:grid-cols-3">
        <flux:card class="space-y-4 lg:col-span-2">
            <div class="flex items-center justify-between">
                <flux:heading>RFQ summary</flux:heading>
                <flux:badge :color="match ($rfq->status) {
                    App\Enums\RequestForQuotationStatus::Draft => 'zinc',
                    App\Enums\RequestForQuotationStatus::Issued => 'blue',
                    App\Enums\RequestForQuotationStatus::Closed => 'amber',
                    App\Enums\RequestForQuotationStatus::Awarded => 'green',
                    App\Enums\RequestForQuotationStatus::Cancelled => 'zinc',
                }">{{ $rfq->status->label() }}</flux:badge>
            </div>

            <dl class="grid gap-4 sm:grid-cols-2">
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Requisition</dt>
                    <dd class="text-sm font-medium">
                        @if ($rfq->purchaseRequisition)
                            <a href="{{ route('procurement.requisitions.show', $rfq->purchaseRequisition) }}" wire:navigate class="hover:underline">{{ $rfq->purchaseRequisition->document_number }}</a>
                        @else
                            Not linked
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Business unit</dt>
                    <dd class="text-sm font-medium">{{ $rfq->businessUnit?->name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Closing date</dt>
                    <dd class="text-sm font-medium">{{ $rfq->closing_date?->format('d M Y') ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Issued</dt>
                    <dd class="text-sm font-medium">{{ $rfq->issued_at?->format('d M Y H:i') ?? 'Not issued' }}</dd>
                </div>
            </dl>

            @if ($rfq->description)
                <flux:text class="text-sm text-zinc-600 dark:text-zinc-300">{{ $rfq->description }}</flux:text>
            @endif
        </flux:card>

        <flux:card class="space-y-4">
            <flux:heading>Progress</flux:heading>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between"><dt class="text-zinc-500 dark:text-zinc-400">Suppliers invited</dt><dd>{{ $rfq->suppliers->count() }}</dd></div>
                <div class="flex justify-between"><dt class="text-zinc-500 dark:text-zinc-400">Quotations received</dt><dd>{{ $rfq->quotations->count() }}</dd></div>
                <div class="flex justify-between"><dt class="text-zinc-500 dark:text-zinc-400">Evaluations</dt><dd>{{ $evaluations->count() }}</dd></div>
            </dl>

            @can('create', App\Domain\Procurement\Models\SupplierQuotation::class)
                @if ($canCreateQuotation)
                    <flux:button class="w-full" variant="primary" icon="document-plus" :href="route('procurement.quotations.create', ['rfq' => $rfq->id])" wire:navigate>Capture quotation</flux:button>
                @endif
            @endcan
        </flux:card>
    </div>

    <flux:card class="space-y-4">
        <div>
            <flux:heading>Lines</flux:heading>
            <flux:subheading>Item package suppliers are asked to price.</flux:subheading>
        </div>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>Item</flux:table.column>
                <flux:table.column>Unit</flux:table.column>
                <flux:table.column align="end">Quantity</flux:table.column>
                <flux:table.column>Specification</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($rfq->items as $item)
                    <flux:table.row :key="$item->id">
                        <flux:table.cell>
                            <div class="font-medium">{{ $item->item?->name }}</div>
                            <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ $item->item?->code }}</div>
                        </flux:table.cell>
                        <flux:table.cell>{{ $item->unitOfMeasure?->code }}</flux:table.cell>
                        <flux:table.cell align="end">{{ rtrim(rtrim($item->quantity, '0'), '.') }}</flux:table.cell>
                        <flux:table.cell>{{ $item->specification ?: '—' }}</flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <div class="grid gap-6 lg:grid-cols-2">
        <flux:card class="space-y-3">
            <div>
                <flux:heading>Invited suppliers</flux:heading>
                <flux:subheading>Response status is updated when quotations are submitted.</flux:subheading>
            </div>
            @forelse ($rfq->suppliers as $invite)
                <div class="flex items-center justify-between rounded-lg border border-zinc-200 p-3 text-sm dark:border-zinc-700">
                    <span class="font-medium">{{ $invite->supplier?->name ?? 'Unknown supplier' }}</span>
                    <flux:badge size="sm" :color="match ($invite->status) {
                        App\Enums\RfqSupplierStatus::Responded => 'green',
                        App\Enums\RfqSupplierStatus::NoResponse => 'red',
                        default => 'zinc',
                    }">{{ $invite->status->label() }}</flux:badge>
                </div>
            @empty
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">No suppliers invited yet.</flux:text>
            @endforelse
        </flux:card>

        <flux:card class="space-y-3">
            <div>
                <flux:heading>Submitted quotations</flux:heading>
                <flux:subheading>Only submitted quotations can be evaluated and awarded.</flux:subheading>
            </div>
            @forelse ($submittedQuotations as $quotation)
                <div class="flex items-center justify-between rounded-lg border border-zinc-200 p-3 text-sm dark:border-zinc-700">
                    <div>
                        <a href="{{ route('procurement.quotations.show', $quotation) }}" wire:navigate class="font-medium hover:underline">{{ $quotation->supplier?->name }}</a>
                        <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ $quotation->document_number }} · {{ $quotation->currency }} {{ number_format((float) $quotation->total_amount, 2) }}</div>
                    </div>
                    <flux:badge size="sm">{{ $quotation->status->label() }}</flux:badge>
                </div>
            @empty
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">No submitted quotations yet.</flux:text>
            @endforelse

            @can('create', App\Domain\Procurement\Models\QuotationEvaluation::class)
                @if ($submittedQuotations->isNotEmpty())
                    <flux:button class="w-full" icon="scale" wire:click="$set('showEvaluationModal', true)">Start evaluation</flux:button>
                @endif
            @endcan
        </flux:card>
    </div>

    <flux:card class="space-y-3">
        <flux:heading>Evaluations</flux:heading>
        @forelse ($evaluations as $evaluation)
            <div class="flex items-center justify-between rounded-lg border border-zinc-200 p-3 text-sm dark:border-zinc-700">
                <div>
                    <a href="{{ route('procurement.evaluations.show', $evaluation) }}" wire:navigate class="font-medium hover:underline">{{ $evaluation->document_number }}</a>
                    <div class="text-xs text-zinc-500 dark:text-zinc-400">
                        Recommended: {{ $evaluation->recommendedQuotation?->supplier?->name ?? '—' }}
                    </div>
                </div>
                <flux:badge size="sm" :color="match ($evaluation->status) {
                    App\Enums\QuotationEvaluationStatus::Approved => 'green',
                    App\Enums\QuotationEvaluationStatus::PendingApproval => 'blue',
                    App\Enums\QuotationEvaluationStatus::Rejected => 'red',
                    App\Enums\QuotationEvaluationStatus::Returned => 'amber',
                    default => 'zinc',
                }">{{ $evaluation->status->label() }}</flux:badge>
            </div>
        @empty
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">No evaluation has been started for this RFQ.</flux:text>
        @endforelse
    </flux:card>

    <flux:modal wire:model="showCancelModal" class="md:w-96">
        <form wire:submit="cancel" class="space-y-4">
            <div>
                <flux:heading size="lg">Cancel RFQ</flux:heading>
                <flux:subheading>Linked requisition conversions are released where safe. This cannot be undone.</flux:subheading>
            </div>
            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showCancelModal', false)">Keep RFQ</flux:button>
                <flux:button type="submit" variant="danger">Cancel RFQ</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model="showEvaluationModal" class="md:w-[34rem]">
        <form wire:submit="createEvaluation" class="space-y-4">
            <div>
                <flux:heading size="lg">Start quotation evaluation</flux:heading>
                <flux:subheading>The recommended quotation is awarded once the evaluation is approved.</flux:subheading>
            </div>

            <flux:select wire:model="evaluationForm.recommended_quotation_id" label="Recommended quotation" required>
                <option value="">Select a quotation</option>
                @foreach ($submittedQuotations as $quotation)
                    <option value="{{ $quotation->id }}">{{ $quotation->supplier?->name }} — {{ $quotation->currency }} {{ number_format((float) $quotation->total_amount, 2) }}</option>
                @endforeach
            </flux:select>

            <flux:textarea wire:model="evaluationForm.comments" label="Evaluation comments" rows="3" />

            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" wire:model="evaluationForm.submit_now" class="rounded border-zinc-300">
                Submit for approval immediately
            </label>

            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showEvaluationModal', false)">Cancel</flux:button>
                <flux:button type="submit" variant="primary">Create evaluation</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
