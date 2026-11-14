<div class="space-y-6">
    <x-page-header title="Material Returns" description="Return project or department material, capture condition assessment, and post only good stock back to availability.">
        <x-slot name="actions">
            @can('create', App\Domain\Distribution\Models\MaterialReturn::class)
                <flux:button variant="primary" icon="plus" :href="route('distribution.returns.create')" wire:navigate>New return</flux:button>
            @endcan
        </x-slot>
    </x-page-header>

    <x-domain-feedback :message="$feedback" :type="$feedbackType" />

    <flux:card>
        <div class="flex flex-wrap items-center gap-3">
            <flux:input wire:model.live.debounce.300ms="search" placeholder="Search number or reason" icon="magnifying-glass" class="max-w-sm" />
            <flux:select wire:model.live="statusFilter" class="max-w-48">
                <option value="">All statuses</option>
                @foreach ($statusOptions as $status)
                    <option value="{{ $status->value }}">{{ $status->label() }}</option>
                @endforeach
            </flux:select>
            <flux:spacer />
            <span class="text-sm text-zinc-500 dark:text-zinc-400">{{ $returns->total() }} returns</span>
        </div>

        <flux:table :paginate="$returns" class="mt-4">
            <flux:table.columns>
                <flux:table.column>Number</flux:table.column>
                <flux:table.column>Type</flux:table.column>
                <flux:table.column>Original issue</flux:table.column>
                <flux:table.column>Warehouse</flux:table.column>
                <flux:table.column>Returned by</flux:table.column>
                <flux:table.column align="end">Lines</flux:table.column>
                <flux:table.column>Status</flux:table.column>
                <flux:table.column align="end">Actions</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($returns as $return)
                    <flux:table.row :key="$return->id">
                        <flux:table.cell variant="strong">
                            <a href="{{ route('distribution.returns.show', $return) }}" wire:navigate class="hover:underline">{{ $return->document_number }}</a>
                        </flux:table.cell>
                        <flux:table.cell>{{ $return->return_type->label() }}</flux:table.cell>
                        <flux:table.cell>
                            @if ($return->originalIssue)
                                <a href="{{ route('distribution.issues.show', $return->originalIssue) }}" wire:navigate class="hover:underline">{{ $return->originalIssue->document_number }}</a>
                            @else
                                —
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>{{ $return->warehouse?->name ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $return->returnedBy?->name ?? '—' }}</flux:table.cell>
                        <flux:table.cell align="end">{{ $return->items_count }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="match ($return->status) {
                                App\Enums\ReturnStatus::Draft => 'zinc',
                                App\Enums\ReturnStatus::PendingInspection => 'blue',
                                App\Enums\ReturnStatus::Assessed => 'amber',
                                App\Enums\ReturnStatus::Posted => 'green',
                                App\Enums\ReturnStatus::Rejected => 'red',
                                App\Enums\ReturnStatus::Cancelled => 'zinc',
                            }">{{ $return->status->label() }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            <div class="flex justify-end gap-1">
                                <flux:button size="sm" variant="ghost" icon="eye" aria-label="Open return" :href="route('distribution.returns.show', $return)" wire:navigate />
                                @can('delete', $return)
                                    <flux:button size="sm" variant="ghost" icon="trash" aria-label="Delete return" wire:click="delete({{ $return->id }})" wire:confirm="Delete this material return?" />
                                @endcan
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="8" class="py-10 text-center text-zinc-500 dark:text-zinc-400">
                            No material returns match your filters.
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>
</div>
