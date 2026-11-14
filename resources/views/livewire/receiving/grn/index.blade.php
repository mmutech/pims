<div class="space-y-6">
    <x-page-header title="Goods Received Notes" description="Posted receipts of accepted inventory; rejected quantities never enter stock." >
        <x-slot name="actions">
            <flux:button variant="ghost" icon="arrow-left" :href="route('receiving.sra.index')" wire:navigate>Receipt advices</flux:button>
        </x-slot>
    </x-page-header>

    <x-domain-feedback :message="$feedback" :type="$feedbackType" />

    <flux:card>
        <div class="flex flex-wrap items-center gap-3">
            <flux:input wire:model.live.debounce.300ms="search" placeholder="Search number, supplier or PO" icon="magnifying-glass" class="max-w-sm" />
            <flux:select wire:model.live="statusFilter" class="max-w-48">
                <option value="">All statuses</option>
                @foreach ($statusOptions as $status)
                    <option value="{{ $status->value }}">{{ $status->label() }}</option>
                @endforeach
            </flux:select>
            <flux:spacer />
            <span class="text-sm text-zinc-500 dark:text-zinc-400">{{ $notes->total() }} notes</span>
        </div>

        <flux:table :paginate="$notes" class="mt-4">
            <flux:table.columns>
                <flux:table.column>Number</flux:table.column>
                <flux:table.column>PO</flux:table.column>
                <flux:table.column>SRA</flux:table.column>
                <flux:table.column>Supplier</flux:table.column>
                <flux:table.column>Warehouse</flux:table.column>
                <flux:table.column>Received</flux:table.column>
                <flux:table.column>Status</flux:table.column>
                <flux:table.column align="end">Actions</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($notes as $grn)
                    <flux:table.row :key="$grn->id">
                        <flux:table.cell variant="strong">
                            <a href="{{ route('receiving.grn.show', $grn) }}" wire:navigate class="hover:underline">{{ $grn->document_number }}</a>
                        </flux:table.cell>
                        <flux:table.cell>{{ $grn->purchaseOrder?->document_number ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $grn->sra?->document_number ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $grn->supplier?->name ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $grn->warehouse?->name ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $grn->received_date?->format('d M Y') ?? '—' }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="match ($grn->status) {
                                App\Enums\GoodsReceivedNoteStatus::Draft => 'zinc',
                                App\Enums\GoodsReceivedNoteStatus::Posted => 'green',
                                App\Enums\GoodsReceivedNoteStatus::Reversed => 'purple',
                                App\Enums\GoodsReceivedNoteStatus::Cancelled => 'red',
                            }">{{ $grn->status->label() }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            <flux:button size="sm" variant="ghost" icon="eye" aria-label="Open goods received note" :href="route('receiving.grn.show', $grn)" wire:navigate />
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="8" class="py-10 text-center text-zinc-500 dark:text-zinc-400">
                            No goods received notes match your filters.
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>
</div>
