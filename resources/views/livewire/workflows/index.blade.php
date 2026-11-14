<div class="space-y-6">
    <x-page-header title="Approval Workflows" description="Database-configured routing for every approvable document type. Workflows are never hardcoded in modules.">
        <x-slot name="actions">
            @can('create', App\Domain\Workflow\Models\ApprovalFlow::class)
                <flux:button variant="primary" icon="plus" :href="route('workflows.create')" wire:navigate>New workflow</flux:button>
            @endcan
        </x-slot>
    </x-page-header>

    <x-domain-feedback :message="$feedback" :type="$feedbackType" />

    <flux:card>
        <div class="flex flex-wrap items-center gap-3">
            <flux:input wire:model.live.debounce.300ms="search" placeholder="Search workflow or document type" icon="magnifying-glass" class="max-w-sm" />
            <flux:select wire:model.live="statusFilter" class="max-w-40">
                <option value="">All statuses</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </flux:select>
            <flux:spacer />
            <span class="text-sm text-zinc-500 dark:text-zinc-400">{{ $flows->total() }} workflows</span>
        </div>

        <flux:table :paginate="$flows" class="mt-4">
            <flux:table.columns>
                <flux:table.column>Workflow</flux:table.column>
                <flux:table.column>Document type</flux:table.column>
                <flux:table.column>Stages</flux:table.column>
                <flux:table.column>Version</flux:table.column>
                <flux:table.column>Requests</flux:table.column>
                <flux:table.column>Status</flux:table.column>
                <flux:table.column align="end">Actions</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($flows as $flow)
                    <flux:table.row :key="$flow->id">
                        <flux:table.cell variant="strong">
                            @can('update', $flow)
                                <a href="{{ route('workflows.edit', $flow) }}" wire:navigate class="hover:underline">{{ $flow->name }}</a>
                            @else
                                {{ $flow->name }}
                            @endcan
                            @if ($flow->description)
                                <div class="max-w-md truncate text-xs font-normal text-zinc-500 dark:text-zinc-400">{{ $flow->description }}</div>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            <span class="font-mono text-xs">{{ $flow->document_type }}</span>
                        </flux:table.cell>
                        <flux:table.cell>{{ $flow->stages_count }}</flux:table.cell>
                        <flux:table.cell>v{{ $flow->version }}</flux:table.cell>
                        <flux:table.cell>{{ $flow->requests_count }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$flow->is_active ? 'green' : 'zinc'">
                                {{ $flow->is_active ? 'Active' : 'Inactive' }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            <div class="flex items-center justify-end gap-1">
                                @can('update', $flow)
                                    <flux:button size="sm" variant="ghost" icon="pencil-square" :href="route('workflows.edit', $flow)" wire:navigate aria-label="Edit workflow" />
                                    @if ($flow->is_active)
                                        <flux:button size="sm" variant="ghost" icon="pause-circle" wire:click="deactivate({{ $flow->id }})" wire:confirm="Deactivate this workflow? New submissions will not route until it is reactivated." aria-label="Deactivate workflow" />
                                    @else
                                        <flux:button size="sm" variant="ghost" icon="play-circle" wire:click="activate({{ $flow->id }})" aria-label="Activate workflow" />
                                    @endif
                                @endcan
                                @can('delete', $flow)
                                    <flux:button size="sm" variant="ghost" icon="trash" wire:click="confirmDelete({{ $flow->id }})" aria-label="Delete workflow" />
                                @endcan
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7" class="py-10 text-center text-zinc-500 dark:text-zinc-400">
                            No approval workflows configured yet.
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <flux:modal wire:model="showDeleteModal" class="md:w-96">
        <form wire:submit="delete" class="space-y-4">
            <div>
                <flux:heading size="lg">Delete workflow</flux:heading>
                <flux:subheading>Delete “{{ $deletingName }}”? Workflows with pending requests cannot be deleted.</flux:subheading>
            </div>
            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showDeleteModal', false)">Cancel</flux:button>
                <flux:button type="submit" variant="danger">Delete workflow</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
