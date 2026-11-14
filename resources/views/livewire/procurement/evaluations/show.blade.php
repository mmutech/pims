<div class="space-y-6">
    <x-page-header :title="$evaluation->document_number" description="Comparative evaluation of quotations received for the RFQ.">
        <x-slot name="actions">
            @if ($evaluation->requestForQuotation)
                <flux:button variant="ghost" icon="arrow-left" :href="route('procurement.rfqs.show', $evaluation->requestForQuotation)" wire:navigate>Back to RFQ</flux:button>
            @endif
            @can('submit', $evaluation)
                <flux:button variant="primary" icon="paper-airplane" wire:click="submit" wire:confirm="Submit this evaluation for approval?">Submit for approval</flux:button>
            @endcan
            @can('cancel', $evaluation)
                @if ($evaluation->status !== App\Enums\QuotationEvaluationStatus::Cancelled)
                    <flux:button variant="danger" icon="x-circle" wire:click="$set('showCancelModal', true)">Cancel</flux:button>
                @endif
            @endcan
        </x-slot>
    </x-page-header>

    <x-domain-feedback :message="$feedback" :type="$feedbackType" />

    <div class="grid gap-6 lg:grid-cols-3">
        <flux:card class="space-y-4 lg:col-span-2">
            <div class="flex items-center justify-between">
                <flux:heading>Evaluation summary</flux:heading>
                <flux:badge :color="match ($evaluation->status) {
                    App\Enums\QuotationEvaluationStatus::Draft => 'zinc',
                    App\Enums\QuotationEvaluationStatus::PendingApproval => 'blue',
                    App\Enums\QuotationEvaluationStatus::Approved => 'green',
                    App\Enums\QuotationEvaluationStatus::Rejected => 'red',
                    App\Enums\QuotationEvaluationStatus::Returned => 'amber',
                    App\Enums\QuotationEvaluationStatus::Cancelled => 'zinc',
                }">{{ $evaluation->status->label() }}</flux:badge>
            </div>

            <dl class="grid gap-4 sm:grid-cols-2">
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Request for quotation</dt>
                    <dd class="text-sm font-medium">
                        @if ($evaluation->requestForQuotation)
                            <a href="{{ route('procurement.rfqs.show', $evaluation->requestForQuotation) }}" wire:navigate class="hover:underline">{{ $evaluation->requestForQuotation->document_number }}</a>
                        @else
                            —
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Recommended supplier</dt>
                    <dd class="text-sm font-medium">{{ $evaluation->recommendedQuotation?->supplier?->name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Recommended value</dt>
                    <dd class="text-sm font-medium">
                        @if ($evaluation->recommendedQuotation)
                            {{ $evaluation->recommendedQuotation->currency }} {{ number_format((float) $evaluation->recommendedQuotation->total_amount, 2) }}
                        @else
                            —
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Evaluated by</dt>
                    <dd class="text-sm font-medium">{{ $evaluation->evaluator?->name ?? 'Not finalised' }}</dd>
                </div>
            </dl>

            @if ($evaluation->comments)
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Comments</dt>
                    <dd class="mt-1 whitespace-pre-line text-sm text-zinc-600 dark:text-zinc-300">{{ $evaluation->comments }}</dd>
                </div>
            @endif

            @if ($evaluation->status === App\Enums\QuotationEvaluationStatus::Approved)
                <flux:callout variant="success" icon="check-circle" heading="Award approved">
                    The recommended quotation has been accepted and all other quotations for the RFQ were rejected.
                </flux:callout>
            @endif
        </flux:card>

        <flux:card class="space-y-4">
            <flux:heading>Approval</flux:heading>
            @if ($approval)
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between"><span class="text-zinc-500 dark:text-zinc-400">Status</span><flux:badge size="sm">{{ $approval->status->label() }}</flux:badge></div>
                    <div class="flex justify-between"><span class="text-zinc-500 dark:text-zinc-400">Requested</span><span>{{ $approval->requested_at?->format('d M Y H:i') }}</span></div>
                </div>
                <flux:button class="w-full" icon="inbox" :href="route('approvals.show', $approval)" wire:navigate>Open approval</flux:button>
            @else
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">No approval request has been raised.</flux:text>
            @endif

            @can('create', App\Domain\Procurement\Models\PurchaseOrder::class)
                @php($accepted = $evaluation->recommendedQuotation)
                @if ($evaluation->status === App\Enums\QuotationEvaluationStatus::Approved && $accepted)
                    <flux:button class="w-full" variant="primary" icon="shopping-cart" :href="route('procurement.orders.create', ['quotation' => $accepted->id])" wire:navigate>Create purchase order</flux:button>
                @endif
            @endcan
        </flux:card>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <flux:card class="space-y-4">
            <div>
                <flux:heading>Recommended quotation lines</flux:heading>
                <flux:subheading>Prices that will carry into the purchase order on award.</flux:subheading>
            </div>

            @if ($evaluation->recommendedQuotation)
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>Item</flux:table.column>
                        <flux:table.column align="end">Quantity</flux:table.column>
                        <flux:table.column align="end">Unit price</flux:table.column>
                        <flux:table.column align="end">Line total</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($evaluation->recommendedQuotation->items as $item)
                            <flux:table.row :key="$item->id">
                                <flux:table.cell>{{ $item->item?->name ?? '—' }}</flux:table.cell>
                                <flux:table.cell align="end">{{ rtrim(rtrim($item->quantity, '0'), '.') }}</flux:table.cell>
                                <flux:table.cell align="end">{{ number_format((float) $item->unit_price, 4) }}</flux:table.cell>
                                <flux:table.cell align="end">{{ number_format((float) $item->line_total, 2) }}</flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @else
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">No quotation selected.</flux:text>
            @endif
        </flux:card>

        <flux:card class="space-y-4">
            <div>
                <flux:heading>Scoring</flux:heading>
                <flux:subheading>Weighted criteria supporting the recommendation.</flux:subheading>
            </div>

            @if ($evaluation->scores->isNotEmpty())
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>Criterion</flux:table.column>
                        <flux:table.column>Supplier</flux:table.column>
                        <flux:table.column align="end">Weight</flux:table.column>
                        <flux:table.column align="end">Score</flux:table.column>
                        <flux:table.column align="end">Weighted</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($evaluation->scores as $score)
                            <flux:table.row :key="$score->id">
                                <flux:table.cell>{{ str($score->criterion)->headline() }}</flux:table.cell>
                                <flux:table.cell>{{ $score->quotation?->supplier?->name ?? '—' }}</flux:table.cell>
                                <flux:table.cell align="end">{{ rtrim(rtrim($score->weight, '0'), '.') }}</flux:table.cell>
                                <flux:table.cell align="end">{{ rtrim(rtrim($score->score, '0'), '.') }}</flux:table.cell>
                                <flux:table.cell align="end">{{ number_format((float) $score->weighted_score, 2) }}</flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @else
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">No scoring was captured. The recommendation is supported by the comments above.</flux:text>
            @endif
        </flux:card>
    </div>

    <flux:modal wire:model="showCancelModal" class="md:w-96">
        <form wire:submit="cancel" class="space-y-4">
            <div>
                <flux:heading size="lg">Cancel evaluation</flux:heading>
                <flux:subheading>The recommended quotation will not be awarded. This cannot be undone.</flux:subheading>
            </div>
            <flux:textarea wire:model="cancellationReason" label="Reason" rows="3" required />
            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showCancelModal', false)">Keep evaluation</flux:button>
                <flux:button type="submit" variant="danger">Cancel evaluation</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
