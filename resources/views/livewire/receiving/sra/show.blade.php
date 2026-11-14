<div class="space-y-6">
    <x-page-header :title="$sra->document_number" :description="$sra->supplier?->name ?? 'Store receipt advice'">
        <x-slot name="actions">
            <flux:button variant="ghost" icon="arrow-left" :href="route('receiving.sra.index')" wire:navigate>All receipt advices</flux:button>

            @can('update', $sra)
                @if ($sra->canBeEdited())
                    <flux:button icon="pencil-square" :href="route('receiving.sra.edit', $sra)" wire:navigate>Edit</flux:button>
                @endif
            @endcan

            @can('submit', $sra)
                <flux:button variant="primary" icon="paper-airplane" wire:click="submit" wire:confirm="Submit this receipt advice for verification?">Submit</flux:button>
            @endcan

            @can('verify', $sra)
                <flux:button variant="primary" icon="check-badge" wire:click="openVerificationModal">Verify delivery</flux:button>
            @endcan

            @can('startInspection', $sra)
                <flux:button variant="primary" icon="beaker" wire:click="startInspection">Start inspection</flux:button>
            @endcan

            @can('createGoodsReceivedNote', $sra)
                <flux:button variant="primary" icon="inbox-arrow-down" wire:click="createGrn" wire:confirm="Create a goods received note from the accepted quantities?">Create GRN</flux:button>
            @endcan

            @can('cancel', $sra)
                <flux:button variant="danger" icon="x-circle" wire:click="$set('showCancelModal', true)">Cancel</flux:button>
            @endcan
        </x-slot>
    </x-page-header>

    <x-domain-feedback :message="$feedback" :type="$feedbackType" />

    <div class="grid gap-6 lg:grid-cols-3">
        <flux:card class="space-y-4 lg:col-span-2">
            <div class="flex items-center justify-between">
                <flux:heading>Receipt summary</flux:heading>
                <flux:badge :color="match ($sra->status) {
                    App\Enums\ReceiptStatus::Draft => 'zinc',
                    App\Enums\ReceiptStatus::PendingVerification => 'blue',
                    App\Enums\ReceiptStatus::Verified => 'green',
                    App\Enums\ReceiptStatus::PendingInspection => 'amber',
                    App\Enums\ReceiptStatus::Inspected => 'lime',
                    App\Enums\ReceiptStatus::Posted => 'teal',
                    App\Enums\ReceiptStatus::Reversed => 'purple',
                    App\Enums\ReceiptStatus::Cancelled => 'red',
                }">{{ $sra->status->label() }}</flux:badge>
            </div>

            <dl class="grid gap-4 sm:grid-cols-2">
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Purchase order</dt>
                    <dd class="text-sm font-medium">
                        @if ($purchaseOrder)
                            <a href="{{ route('procurement.orders.show', $purchaseOrder) }}" wire:navigate class="hover:underline">{{ $purchaseOrder->document_number }}</a>
                        @else
                            —
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Warehouse</dt>
                    <dd class="text-sm font-medium">{{ $sra->warehouse?->name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Expected delivery</dt>
                    <dd class="text-sm font-medium">{{ $sra->expected_delivery_date?->format('d M Y') ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Delivery note</dt>
                    <dd class="text-sm font-medium">{{ $sra->delivery_note_number ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Vehicle</dt>
                    <dd class="text-sm font-medium">{{ $sra->vehicle_number ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Driver</dt>
                    <dd class="text-sm font-medium">{{ $sra->driver_name ?: '—' }} {{ $sra->driver_phone ? '('.$sra->driver_phone.')' : '' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Received by</dt>
                    <dd class="text-sm font-medium">{{ $sra->receiver?->name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Verified</dt>
                    <dd class="text-sm font-medium">{{ $sra->verified_at?->format('d M Y H:i') ?? 'Not yet' }}</dd>
                </div>
            </dl>

            @if ($sra->cancellation_reason)
                <flux:callout variant="danger" icon="x-circle" heading="Cancelled">
                    {{ $sra->cancellation_reason }}
                </flux:callout>
            @endif
        </flux:card>

        <flux:card class="space-y-3">
            <flux:heading>Gate status</flux:heading>

            <div class="space-y-2 text-sm">
                <div class="flex items-center justify-between">
                    <span class="text-zinc-500 dark:text-zinc-400">Delivery verification</span>
                    <flux:badge size="sm" :color="$verification ? 'green' : 'zinc'">{{ $verification?->status->label() ?? 'Pending' }}</flux:badge>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-zinc-500 dark:text-zinc-400">Quality inspection</span>
                    <flux:badge size="sm" :color="$requiresInspection ? (($sra->completedInspection()) ? 'green' : 'amber') : 'zinc'">
                        {{ $requiresInspection ? ($sra->completedInspection()?->status->label() ?? 'Required') : 'Not required' }}
                    </flux:badge>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-zinc-500 dark:text-zinc-400">Goods received note</span>
                    <flux:badge size="sm" :color="$grns->isNotEmpty() ? 'green' : 'zinc'">{{ $grns->isNotEmpty() ? $grns->first()->status->label() : 'None' }}</flux:badge>
                </div>
            </div>

            <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">
                Rejected goods are recorded for supplier follow-up and never enter available stock.
            </flux:text>
        </flux:card>
    </div>

    <flux:card class="space-y-4">
        <div>
            <flux:heading>Delivery lines</flux:heading>
            <flux:subheading>Expected versus delivered quantities as advised by the supplier.</flux:subheading>
        </div>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>Item</flux:table.column>
                <flux:table.column>Unit</flux:table.column>
                <flux:table.column align="end">Expected</flux:table.column>
                <flux:table.column align="end">Delivered</flux:table.column>
                <flux:table.column>Batch</flux:table.column>
                <flux:table.column>Serials</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($sra->items as $item)
                    <flux:table.row :key="$item->id">
                        <flux:table.cell>
                            <div class="font-medium">{{ $item->item?->name }}</div>
                            <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ $item->item?->code }}</div>
                        </flux:table.cell>
                        <flux:table.cell>{{ $item->unitOfMeasure?->code }}</flux:table.cell>
                        <flux:table.cell align="end">{{ rtrim(rtrim($item->expected_quantity, '0'), '.') }}</flux:table.cell>
                        <flux:table.cell align="end">{{ rtrim(rtrim($item->delivered_quantity, '0'), '.') }}</flux:table.cell>
                        <flux:table.cell>{{ $item->batch_number ?: '—' }}</flux:table.cell>
                        <flux:table.cell>
                            @if (! empty($item->serial_numbers))
                                <span class="text-xs">{{ implode(', ', $item->serial_numbers) }}</span>
                            @else
                                —
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </flux:card>

    @if ($verification)
        <flux:card class="space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <flux:heading>{{ $verification->document_number }}</flux:heading>
                    <flux:subheading>Verified {{ $verification->verified_at?->format('d M Y H:i') }} by {{ $verification->verifier?->name ?? '—' }}</flux:subheading>
                </div>
                <flux:badge :color="$verification->status->isComplete() ? 'green' : 'amber'">{{ $verification->status->label() }}</flux:badge>
            </div>

            @if ($verification->discrepancy_summary)
                <flux:callout icon="exclamation-triangle" variant="warning">{{ $verification->discrepancy_summary }}</flux:callout>
            @endif

            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Line</flux:table.column>
                    <flux:table.column align="end">Verified</flux:table.column>
                    <flux:table.column align="end">Damaged</flux:table.column>
                    <flux:table.column align="end">Short</flux:table.column>
                    <flux:table.column>Condition</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($verification->items as $line)
                        <flux:table.row :key="$line->id">
                            <flux:table.cell>{{ $line->sraItem?->item?->name ?? 'Line #'.$line->sra_item_id }}</flux:table.cell>
                            <flux:table.cell align="end">{{ rtrim(rtrim($line->verified_quantity, '0'), '.') }}</flux:table.cell>
                            <flux:table.cell align="end">{{ rtrim(rtrim($line->damaged_quantity, '0'), '.') }}</flux:table.cell>
                            <flux:table.cell align="end">{{ rtrim(rtrim($line->short_quantity, '0'), '.') }}</flux:table.cell>
                            <flux:table.cell>{{ $line->condition->label() }}</flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </flux:card>
    @endif

    @forelse ($inspections as $inspection)
        <flux:card class="space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <flux:heading>{{ $inspection->document_number }}</flux:heading>
                    <flux:subheading>Inspector: {{ $inspection->inspector?->name ?? '—' }} · {{ $inspection->inspection_date?->format('d M Y') }}</flux:subheading>
                </div>
                <div class="flex items-center gap-2">
                    <flux:badge :color="$inspection->isComplete() ? 'green' : 'amber'">{{ $inspection->status->label() }}</flux:badge>
                    @can('record', $inspection)
                        <flux:button size="sm" icon="pencil-square" wire:click="openInspectionModal({{ $inspection->id }})">Record result</flux:button>
                    @endcan
                </div>
            </div>

            @if ($inspection->summary)
                <flux:text class="text-sm">{{ $inspection->summary }}</flux:text>
            @endif

            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Line</flux:table.column>
                    <flux:table.column align="end">Inspected</flux:table.column>
                    <flux:table.column align="end">Accepted</flux:table.column>
                    <flux:table.column align="end">Rejected</flux:table.column>
                    <flux:table.column align="end">Quarantined</flux:table.column>
                    <flux:table.column>Result</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($inspection->items as $line)
                        <flux:table.row :key="$line->id">
                            <flux:table.cell>{{ $line->sraItem?->item?->name ?? 'Line #'.$line->sra_item_id }}</flux:table.cell>
                            <flux:table.cell align="end">{{ rtrim(rtrim($line->inspected_quantity, '0'), '.') }}</flux:table.cell>
                            <flux:table.cell align="end">{{ rtrim(rtrim($line->accepted_quantity, '0'), '.') }}</flux:table.cell>
                            <flux:table.cell align="end">{{ rtrim(rtrim($line->rejected_quantity, '0'), '.') }}</flux:table.cell>
                            <flux:table.cell align="end">{{ rtrim(rtrim($line->quarantined_quantity, '0'), '.') }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm" :color="match ($line->result) {
                                    App\Enums\InspectionResult::Pending => 'zinc',
                                    App\Enums\InspectionResult::Passed => 'green',
                                    App\Enums\InspectionResult::Failed => 'red',
                                    App\Enums\InspectionResult::PartiallyPassed => 'amber',
                                }">{{ $line->result->label() }}</flux:badge>
                            </flux:table.cell>
                        </flux:table.row>

                        @foreach ($line->findings as $finding)
                            <flux:table.row :key="'finding-'.$finding->id">
                                <flux:table.cell colspan="5" class="text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ $finding->severity->label() }} {{ $finding->finding_type->label() }}: {{ $finding->description }}
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </flux:card>
    @empty
        <flux:card class="space-y-2">
            <flux:heading>Quality inspection</flux:heading>
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                {{ $requiresInspection ? 'This delivery contains items that require inspection.' : 'No inspection is required for this delivery.' }}
            </flux:text>
        </flux:card>
    @endforelse

    <flux:card class="space-y-4">
        <div>
            <flux:heading>Goods received notes</flux:heading>
            <flux:subheading>Posting a GRN moves accepted quantities into stock; rejected quantities stay out.</flux:subheading>
        </div>

        @forelse ($grns as $grn)
            <div class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                <div>
                    <a href="{{ route('receiving.grn.show', $grn) }}" wire:navigate class="text-sm font-semibold hover:underline">{{ $grn->document_number }}</a>
                    <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ $grn->received_date?->format('d M Y') }}</div>
                </div>
                <div class="flex items-center gap-2">
                    <flux:badge size="sm" :color="match ($grn->status) {
                        App\Enums\GoodsReceivedNoteStatus::Draft => 'zinc',
                        App\Enums\GoodsReceivedNoteStatus::Posted => 'green',
                        App\Enums\GoodsReceivedNoteStatus::Reversed => 'purple',
                        App\Enums\GoodsReceivedNoteStatus::Cancelled => 'red',
                    }">{{ $grn->status->label() }}</flux:badge>
                    <flux:button size="sm" variant="ghost" icon="eye" :href="route('receiving.grn.show', $grn)" wire:navigate>Open</flux:button>
                </div>
            </div>
        @empty
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">No goods received note has been created yet.</flux:text>
        @endforelse
    </flux:card>

    <flux:modal wire:model="showCancelModal" class="md:w-96">
        <form wire:submit="cancel" class="space-y-4">
            <div>
                <flux:heading size="lg">Cancel receipt advice</flux:heading>
                <flux:subheading>The delivery will not be received into stock. History is retained.</flux:subheading>
            </div>
            <flux:textarea wire:model="cancellationReason" label="Reason" rows="3" required />
            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showCancelModal', false)">Keep advice</flux:button>
                <flux:button type="submit" variant="danger">Cancel advice</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model="showVerificationModal" class="md:w-[48rem]">
        <form wire:submit="verify" class="space-y-4">
            <div>
                <flux:heading size="lg">Verify delivery</flux:heading>
                <flux:subheading>Record the quantity physically received and any damage. Every line must be verified.</flux:subheading>
            </div>

            <div class="space-y-3">
                @foreach ($verificationLines as $index => $line)
                    <div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                        <div class="flex items-center justify-between">
                            <div class="text-sm font-medium">{{ $line['label'] }}</div>
                            <div class="text-xs text-zinc-500 dark:text-zinc-400">Delivered {{ rtrim(rtrim($line['delivered_quantity'], '0'), '.') }}</div>
                        </div>
                        <div class="mt-2 grid gap-3 sm:grid-cols-3">
                            <flux:input wire:model="verificationLines.{{ $index }}.verified_quantity" type="number" step="0.0001" min="0" label="Verified quantity" required />
                            <flux:input wire:model="verificationLines.{{ $index }}.damaged_quantity" type="number" step="0.0001" min="0" label="Damaged quantity" required />
                            <flux:select wire:model="verificationLines.{{ $index }}.condition" label="Condition">
                                @foreach ($conditionOptions as $condition)
                                    <option value="{{ $condition->value }}">{{ $condition->label() }}</option>
                                @endforeach
                            </flux:select>
                        </div>
                        <flux:input wire:model="verificationLines.{{ $index }}.notes" label="Notes" class="mt-2" />
                    </div>
                @endforeach
            </div>

            <flux:textarea wire:model="discrepancySummary" label="Discrepancy summary" rows="2" />

            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showVerificationModal', false)">Cancel</flux:button>
                <flux:button type="submit" variant="primary">Save verification</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model="showInspectionModal" class="md:w-[52rem]">
        <form wire:submit="recordInspection" class="space-y-4">
            <div>
                <flux:heading size="lg">Record inspection result</flux:heading>
                <flux:subheading>Accepted, rejected and quarantined quantities must equal the inspected quantity on every line.</flux:subheading>
            </div>

            <div class="grid gap-3 sm:grid-cols-3">
                <flux:select wire:model="inspectionMethod" label="Method">
                    <option value="">Not specified</option>
                    @foreach ($methodOptions as $method)
                        <option value="{{ $method->value }}">{{ $method->label() }}</option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="inspectionSampleSize" type="number" step="0.0001" min="0" label="Sample size" />
                <flux:input wire:model="inspectionSummary" label="Summary" />
            </div>

            <div class="space-y-3">
                @foreach ($inspectionLines as $index => $line)
                    <div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                        <div class="flex items-center justify-between">
                            <div class="text-sm font-medium">{{ $line['label'] }}</div>
                            <div class="text-xs text-zinc-500 dark:text-zinc-400">Verified {{ rtrim(rtrim($line['inspected_quantity'], '0'), '.') }}</div>
                        </div>
                        <div class="mt-2 grid gap-3 sm:grid-cols-4">
                            <flux:input wire:model="inspectionLines.{{ $index }}.inspected_quantity" type="number" step="0.0001" min="0" label="Inspected" required />
                            <flux:input wire:model="inspectionLines.{{ $index }}.accepted_quantity" type="number" step="0.0001" min="0" label="Accepted" required />
                            <flux:input wire:model="inspectionLines.{{ $index }}.rejected_quantity" type="number" step="0.0001" min="0" label="Rejected" required />
                            <flux:input wire:model="inspectionLines.{{ $index }}.quarantined_quantity" type="number" step="0.0001" min="0" label="Quarantined" required />
                        </div>
                        <flux:input wire:model="inspectionLines.{{ $index }}.notes" label="Notes" class="mt-2" />
                    </div>
                @endforeach
            </div>

            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showInspectionModal', false)">Cancel</flux:button>
                <flux:button type="submit" variant="primary">Save result</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
