<div class="space-y-6">
    <x-page-header :title="$issue->document_number" :description="$issue->requisition ? 'Issued against '.$issue->requisition->document_number : 'Stock issue'">
        <x-slot name="actions">
            <flux:button variant="ghost" icon="arrow-left" :href="route('distribution.issues.index')" wire:navigate>All issues</flux:button>
            @can('post', $issue)
                <flux:button variant="primary" icon="arrow-up-tray" wire:click="post" wire:confirm="Post this issue? Inventory will be deducted immediately and cannot be deleted.">Post issue</flux:button>
            @endcan
            @can('acknowledge', $issue)
                <flux:button variant="primary" icon="check-badge" wire:click="$set('showAcknowledgeModal', true)">Acknowledge receipt</flux:button>
            @endcan
            @can('reverse', $issue)
                <flux:button variant="danger" icon="arrow-uturn-left" wire:click="$set('showReversalModal', true)">Reverse</flux:button>
            @endcan
            @can('cancel', $issue)
                <flux:button variant="danger" icon="x-circle" wire:click="$set('showCancelModal', true)">Cancel</flux:button>
            @endcan
            @can('delete', $issue)
                <flux:button variant="danger" icon="trash" wire:click="delete" wire:confirm="Delete this draft issue?">Delete</flux:button>
            @endcan
        </x-slot>
    </x-page-header>

    <x-domain-feedback :message="$feedback" :type="$feedbackType" />

    <div class="grid gap-6 lg:grid-cols-3">
        <flux:card class="space-y-4 lg:col-span-2">
            <div class="flex items-center justify-between">
                <flux:heading>Issue summary</flux:heading>
                <flux:badge :color="match ($issue->status) {
                    App\Enums\IssueStatus::Draft => 'zinc',
                    App\Enums\IssueStatus::Posted => 'green',
                    App\Enums\IssueStatus::Reversed => 'red',
                    App\Enums\IssueStatus::Cancelled => 'zinc',
                }">{{ $issue->status->label() }}</flux:badge>
            </div>

            <dl class="grid gap-4 sm:grid-cols-2">
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Requisition</dt>
                    <dd class="text-sm font-medium">
                        @if ($issue->requisition)
                            <a href="{{ route('distribution.requisitions.show', $issue->requisition) }}" wire:navigate class="hover:underline">{{ $issue->requisition->document_number }}</a>
                        @else
                            —
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Warehouse</dt>
                    <dd class="text-sm font-medium">{{ $issue->warehouse?->name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Recipient</dt>
                    <dd class="text-sm font-medium">{{ $issue->recipient?->name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Issued by</dt>
                    <dd class="text-sm font-medium">{{ $issue->issuer?->name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Issue date</dt>
                    <dd class="text-sm font-medium">{{ $issue->issue_date?->format('d M Y') ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Posted</dt>
                    <dd class="text-sm font-medium">{{ $issue->posted_at?->format('d M Y H:i') ?? 'Not posted' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Project</dt>
                    <dd class="text-sm font-medium">{{ $issue->requisition?->project?->name ?? 'Not project-funded' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Department</dt>
                    <dd class="text-sm font-medium">{{ $issue->requisition?->requestingDepartment?->name ?? '—' }}</dd>
                </div>
            </dl>
        </flux:card>

        <flux:card class="space-y-4">
            <flux:heading>Acknowledgement</flux:heading>

            @if ($issue->acknowledgement)
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between"><span class="text-zinc-500 dark:text-zinc-400">By</span><span class="font-medium">{{ $issue->acknowledgement->acknowledgedBy?->name ?? '—' }}</span></div>
                    <div class="flex justify-between"><span class="text-zinc-500 dark:text-zinc-400">At</span><span>{{ $issue->acknowledgement->acknowledged_at?->format('d M Y H:i') }}</span></div>
                    @if ($issue->acknowledgement->comment)
                        <div>
                            <div class="text-zinc-500 dark:text-zinc-400">Comment</div>
                            <p class="mt-1">{{ $issue->acknowledgement->comment }}</p>
                        </div>
                    @endif
                </div>
            @elseif ($issue->status === App\Enums\IssueStatus::Posted)
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                    The recipient has not acknowledged receipt yet. Only the recipient can acknowledge, unless an override is granted.
                </flux:text>
            @else
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                    Acknowledgements are captured after the issue is posted.
                </flux:text>
            @endif
        </flux:card>
    </div>

    <flux:card class="space-y-4">
        <div>
            <flux:heading>Issued lines</flux:heading>
            <flux:subheading>Costs are valued at the moment of posting from the inventory valuation layer.</flux:subheading>
        </div>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>Item</flux:table.column>
                <flux:table.column>Unit</flux:table.column>
                <flux:table.column>Batch</flux:table.column>
                <flux:table.column align="end">Quantity</flux:table.column>
                <flux:table.column align="end">Unit cost</flux:table.column>
                <flux:table.column align="end">Total cost</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($issue->items as $item)
                    <flux:table.row :key="$item->id">
                        <flux:table.cell>
                            <div class="font-medium">{{ $item->item?->name }}</div>
                            <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ $item->item?->code }}</div>
                        </flux:table.cell>
                        <flux:table.cell>{{ $item->unitOfMeasure?->code }}</flux:table.cell>
                        <flux:table.cell>{{ $item->batch?->batch_number ?? '—' }}</flux:table.cell>
                        <flux:table.cell align="end">{{ rtrim(rtrim($item->issued_quantity, '0'), '.') }}</flux:table.cell>
                        <flux:table.cell align="end">{{ number_format((float) $item->unit_cost, 4) }}</flux:table.cell>
                        <flux:table.cell align="end">{{ number_format((float) $item->total_cost, 2) }}</flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <flux:modal wire:model="showReversalModal" class="md:w-96">
        <form wire:submit="reverse" class="space-y-4">
            <div>
                <flux:heading size="lg">Reverse stock issue</flux:heading>
                <flux:subheading>A reversing ledger entry is posted. The original issue remains in history.</flux:subheading>
            </div>
            <flux:textarea wire:model="reversalReason" label="Reason" rows="3" required />
            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showReversalModal', false)">Keep issue</flux:button>
                <flux:button type="submit" variant="danger">Reverse issue</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model="showCancelModal" class="md:w-96">
        <form wire:submit="cancel" class="space-y-4">
            <div>
                <flux:heading size="lg">Cancel draft issue</flux:heading>
                <flux:subheading>The draft will be cancelled and remain auditable.</flux:subheading>
            </div>
            <flux:textarea wire:model="cancellationReason" label="Reason" rows="3" required />
            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showCancelModal', false)">Keep draft</flux:button>
                <flux:button type="submit" variant="danger">Cancel issue</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model="showAcknowledgeModal" class="md:w-96">
        <form wire:submit="acknowledge" class="space-y-4">
            <div>
                <flux:heading size="lg">Acknowledge receipt</flux:heading>
                <flux:subheading>Confirm that the material listed on this issue has been received.</flux:subheading>
            </div>
            <flux:textarea wire:model="acknowledgementComment" label="Comment" rows="3" />
            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showAcknowledgeModal', false)">Cancel</flux:button>
                <flux:button type="submit" variant="primary">Acknowledge</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
