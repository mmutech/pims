<div class="space-y-6">
    <x-page-header :title="$materialReturn->document_number" :description="$materialReturn->reason">
        <x-slot name="actions">
            <flux:button variant="ghost" icon="arrow-left" :href="route('distribution.returns.index')" wire:navigate>All returns</flux:button>
            @can('update', $materialReturn)
                <flux:button icon="pencil-square" :href="route('distribution.returns.edit', $materialReturn)" wire:navigate>Edit</flux:button>
            @endcan
            @can('assess', $materialReturn)
                <flux:button variant="primary" icon="clipboard-document-check" wire:click="openAssessmentModal">Assess condition</flux:button>
            @endcan
            @can('post', $materialReturn)
                <flux:button variant="primary" icon="arrow-down-tray" wire:click="post" wire:confirm="Post this return? Only accepted good-condition quantities will re-enter available stock.">Post return</flux:button>
            @endcan
            @can('cancel', $materialReturn)
                <flux:button variant="danger" icon="x-circle" wire:click="$set('showCancelModal', true)">Cancel</flux:button>
            @endcan
            @can('delete', $materialReturn)
                <flux:button variant="danger" icon="trash" wire:click="delete" wire:confirm="Delete this material return?">Delete</flux:button>
            @endcan
        </x-slot>
    </x-page-header>

    <x-domain-feedback :message="$feedback" :type="$feedbackType" />

    <div class="grid gap-6 lg:grid-cols-3">
        <flux:card class="space-y-4 lg:col-span-2">
            <div class="flex items-center justify-between">
                <flux:heading>Return summary</flux:heading>
                <flux:badge :color="match ($materialReturn->status) {
                    App\Enums\ReturnStatus::Draft => 'zinc',
                    App\Enums\ReturnStatus::PendingInspection => 'blue',
                    App\Enums\ReturnStatus::Assessed => 'amber',
                    App\Enums\ReturnStatus::Posted => 'green',
                    App\Enums\ReturnStatus::Rejected => 'red',
                    App\Enums\ReturnStatus::Cancelled => 'zinc',
                }">{{ $materialReturn->status->label() }}</flux:badge>
            </div>

            <dl class="grid gap-4 sm:grid-cols-2">
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Return type</dt>
                    <dd class="text-sm font-medium">{{ $materialReturn->return_type->label() }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Original issue</dt>
                    <dd class="text-sm font-medium">
                        @if ($materialReturn->originalIssue)
                            <a href="{{ route('distribution.issues.show', $materialReturn->originalIssue) }}" wire:navigate class="hover:underline">{{ $materialReturn->originalIssue->document_number }}</a>
                        @else
                            —
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Warehouse</dt>
                    <dd class="text-sm font-medium">{{ $materialReturn->warehouse?->name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Returned by</dt>
                    <dd class="text-sm font-medium">{{ $materialReturn->returnedBy?->name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Posted</dt>
                    <dd class="text-sm font-medium">{{ $materialReturn->posted_at?->format('d M Y H:i') ?? 'Not posted' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Project</dt>
                    <dd class="text-sm font-medium">{{ $materialReturn->originalIssue?->requisition?->project?->name ?? 'Not project-funded' }}</dd>
                </div>
            </dl>
        </flux:card>

        <flux:card class="space-y-4">
            <flux:heading>Assessment rule</flux:heading>
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                Accepted plus rejected quantities must equal the returned quantity on every line.
                Only good condition accepted quantities can return to available stock; damaged,
                defective, quarantined, and repairable material is held in quarantine.
            </flux:text>
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                The person who created or returned the material cannot assess it unless an override is granted.
            </flux:text>
        </flux:card>
    </div>

    <flux:card class="space-y-4">
        <div>
            <flux:heading>Return lines</flux:heading>
            <flux:subheading>Condition and assessment results drive the destination dimension on posting.</flux:subheading>
        </div>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>Item</flux:table.column>
                <flux:table.column>Unit</flux:table.column>
                <flux:table.column>Batch</flux:table.column>
                <flux:table.column align="end">Returned</flux:table.column>
                <flux:table.column>Condition</flux:table.column>
                <flux:table.column align="end">Accepted</flux:table.column>
                <flux:table.column align="end">Rejected</flux:table.column>
                <flux:table.column>Assessment</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($materialReturn->items as $item)
                    @php($assessment = $item->assessments->sortByDesc('id')->first())
                    <flux:table.row :key="$item->id">
                        <flux:table.cell>
                            <div class="font-medium">{{ $item->item?->name }}</div>
                            <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ $item->item?->code }}</div>
                        </flux:table.cell>
                        <flux:table.cell>{{ $item->unitOfMeasure?->code }}</flux:table.cell>
                        <flux:table.cell>{{ $item->batch?->batch_number ?? '—' }}</flux:table.cell>
                        <flux:table.cell align="end">{{ rtrim(rtrim($item->returned_quantity, '0'), '.') }}</flux:table.cell>
                        <flux:table.cell>{{ $item->condition->label() }}</flux:table.cell>
                        <flux:table.cell align="end">{{ rtrim(rtrim($item->accepted_quantity, '0'), '.') }}</flux:table.cell>
                        <flux:table.cell align="end">{{ rtrim(rtrim($item->rejected_quantity, '0'), '.') }}</flux:table.cell>
                        <flux:table.cell>
                            @if ($assessment)
                                <div class="text-sm">{{ $assessment->disposition->label() }}</div>
                                <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ $assessment->assessor?->name }} · {{ $assessment->assessed_at?->format('d M Y') }}</div>
                            @else
                                <span class="text-zinc-500 dark:text-zinc-400">Pending</span>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <flux:modal wire:model="showAssessmentModal" class="md:w-[60rem]">
        <form wire:submit="assess" class="space-y-4">
            <div>
                <flux:heading size="lg">Condition assessment</flux:heading>
                <flux:subheading>Accepted and rejected quantities must add up to the returned quantity.</flux:subheading>
            </div>

            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Item</flux:table.column>
                    <flux:table.column align="end">Returned</flux:table.column>
                    <flux:table.column>Condition</flux:table.column>
                    <flux:table.column>Disposition</flux:table.column>
                    <flux:table.column align="end">Accepted</flux:table.column>
                    <flux:table.column align="end">Rejected</flux:table.column>
                    <flux:table.column>Findings</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($assessmentLines as $index => $line)
                        <flux:table.row :key="$line['material_return_item_id']">
                            <flux:table.cell>
                                <div class="font-medium">{{ $line['item'] }}</div>
                                <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ $line['code'] }}</div>
                            </flux:table.cell>
                            <flux:table.cell align="end">{{ rtrim(rtrim($line['returned_quantity'], '0'), '.') }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:select wire:model="assessmentLines.{{ $index }}.condition">
                                    @foreach ($conditions as $condition)
                                        <option value="{{ $condition->value }}">{{ $condition->label() }}</option>
                                    @endforeach
                                </flux:select>
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:select wire:model="assessmentLines.{{ $index }}.disposition">
                                    @foreach ($dispositions as $disposition)
                                        <option value="{{ $disposition->value }}">{{ $disposition->label() }}</option>
                                    @endforeach
                                </flux:select>
                            </flux:table.cell>
                            <flux:table.cell align="end">
                                <flux:input wire:model="assessmentLines.{{ $index }}.accepted_quantity" type="number" step="0.0001" min="0" class="ml-auto w-24" />
                            </flux:table.cell>
                            <flux:table.cell align="end">
                                <flux:input wire:model="assessmentLines.{{ $index }}.rejected_quantity" type="number" step="0.0001" min="0" class="ml-auto w-24" />
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:input wire:model="assessmentLines.{{ $index }}.findings" placeholder="Findings" />
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>

            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showAssessmentModal', false)">Cancel</flux:button>
                <flux:button type="submit" variant="primary">Save assessment</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model="showCancelModal" class="md:w-96">
        <form wire:submit="cancel" class="space-y-4">
            <div>
                <flux:heading size="lg">Cancel material return</flux:heading>
                <flux:subheading>The return will be cancelled and remain auditable.</flux:subheading>
            </div>
            <flux:textarea wire:model="cancellationReason" label="Reason" rows="3" required />
            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showCancelModal', false)">Keep return</flux:button>
                <flux:button type="submit" variant="danger">Cancel return</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
