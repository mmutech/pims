<div class="space-y-6">
    <x-page-header title="Document Sequences" description="Numbering formats for procurement, warehouse, and stock documents." />

    <x-domain-feedback :message="$feedback" :type="$feedbackType" />

    <flux:card>
        <div class="flex flex-wrap items-center gap-3">
            <flux:input wire:model.live.debounce.300ms="search" placeholder="Search type, prefix, or year" icon="magnifying-glass" class="max-w-sm" />
            <flux:spacer />
            <span class="text-sm text-zinc-500 dark:text-zinc-400">{{ $sequences->total() }} sequences</span>
        </div>

        <flux:table :paginate="$sequences" class="mt-4">
            <flux:table.columns>
                <flux:table.column>Document type</flux:table.column>
                <flux:table.column>Prefix</flux:table.column>
                <flux:table.column>Year</flux:table.column>
                <flux:table.column>Next number</flux:table.column>
                <flux:table.column>Preview</flux:table.column>
                <flux:table.column>Reset policy</flux:table.column>
                <flux:table.column align="end">Actions</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($sequences as $sequence)
                    <flux:table.row :key="$sequence->id">
                        <flux:table.cell variant="strong">
                            {{ $documentTypes[$sequence->document_type] ?? $sequence->document_type }}
                        </flux:table.cell>
                        <flux:table.cell>{{ $sequence->prefix }}</flux:table.cell>
                        <flux:table.cell>{{ $sequence->year }}</flux:table.cell>
                        <flux:table.cell>{{ $sequence->next_number }}</flux:table.cell>
                        <flux:table.cell>
                            <span class="font-mono text-xs text-zinc-700 dark:text-zinc-200">{{ $sequence->preview() }}</span>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" color="zinc">
                                {{ $resetPolicies[$sequence->reset_policy] ?? $sequence->reset_policy }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            @can('update', $sequence)
                                <flux:button size="sm" variant="ghost" icon="pencil-square" aria-label="Edit sequence" wire:click="openEdit({{ $sequence->id }})" />
                            @endcan
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7" class="py-10 text-center text-zinc-500 dark:text-zinc-400">
                            No document sequences match your search.
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <flux:modal wire:model="showForm" class="md:w-[32rem]">
        <form wire:submit="save" class="space-y-4">
            <div>
                <flux:heading size="lg">Edit sequence</flux:heading>
                <flux:subheading>
                    @if ($editing)
                        {{ $documentTypes[$editing->document_type] ?? $editing->document_type }} · {{ $editing->prefix }}/{{ $editing->year }}
                    @endif
                </flux:subheading>
            </div>

            <flux:input wire:model="form.next_number" type="number" min="1" label="Next number" description="The next number to be issued for this sequence." />
            <flux:input wire:model.live="form.padding" type="number" min="1" max="10" label="Padding" description="Total digits used for the numeric segment." />
            <flux:select wire:model="form.reset_policy" label="Reset policy">
                @foreach ($resetPolicies as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </flux:select>

            @if ($editing)
                <div class="rounded-lg border border-zinc-200 bg-zinc-50 p-3 dark:border-zinc-700 dark:bg-zinc-800">
                    <div class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Preview</div>
                    <div class="mt-1 font-mono text-sm text-zinc-800 dark:text-zinc-100">
                        {{ $editing->prefix }}/{{ $editing->year }}/{{ str_pad((string) $form['next_number'], (int) $form['padding'], '0', STR_PAD_LEFT) }}
                    </div>
                </div>
            @endif

            <div class="flex justify-end gap-2 pt-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showForm', false)">Cancel</flux:button>
                <flux:button type="submit" variant="primary">Save sequence</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
