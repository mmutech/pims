<div class="space-y-6">
    <x-page-header title="Item Categories" description="Hierarchical classification for catalog items and stock reporting.">
        <x-slot name="actions">
            @can('create', App\Domain\Catalog\Models\ItemCategory::class)
                <flux:button variant="primary" icon="plus" wire:click="openCreate">New category</flux:button>
            @endcan
        </x-slot>
    </x-page-header>

    <x-domain-feedback :message="$feedback" :type="$feedbackType" />

    <flux:card>
        <div class="flex flex-wrap items-center gap-3">
            <flux:input wire:model.live.debounce.300ms="search" placeholder="Search categories" icon="magnifying-glass" class="max-w-sm" />
            <flux:spacer />
            <span class="text-sm text-zinc-500 dark:text-zinc-400">{{ $categories->total() }} categories</span>
        </div>

        <flux:table :paginate="$categories" class="mt-4">
            <flux:table.columns>
                <flux:table.column>Code</flux:table.column>
                <flux:table.column>Category</flux:table.column>
                <flux:table.column>Parent</flux:table.column>
                <flux:table.column>Items</flux:table.column>
                <flux:table.column>Tracking</flux:table.column>
                <flux:table.column>Status</flux:table.column>
                <flux:table.column align="end">Actions</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($categories as $category)
                    <flux:table.row :key="$category->id">
                        <flux:table.cell variant="strong">{{ $category->code }}</flux:table.cell>
                        <flux:table.cell>
                            <div class="font-medium text-zinc-800 dark:text-zinc-100">{{ $category->name }}</div>
                            @if ($category->description)
                                <div class="max-w-xs truncate text-xs text-zinc-500 dark:text-zinc-400">{{ $category->description }}</div>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>{{ $category->parent?->name ?? '—' }}</flux:table.cell>
                        <flux:table.cell>
                            <div>{{ $category->items_count }}</div>
                            <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ $category->children_count }} subcategories</div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex flex-wrap gap-1">
                                @if ($category->track_batches)
                                    <flux:badge size="sm" color="blue">Batches</flux:badge>
                                @endif
                                @if ($category->track_serials)
                                    <flux:badge size="sm" color="purple">Serials</flux:badge>
                                @endif
                                @if (! $category->track_batches && ! $category->track_serials)
                                    <span class="text-xs text-zinc-500 dark:text-zinc-400">None</span>
                                @endif
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$category->is_active ? 'green' : 'zinc'">
                                {{ $category->is_active ? 'Active' : 'Inactive' }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            <div class="flex justify-end gap-1">
                                @can('update', $category)
                                    <flux:button size="sm" variant="ghost" icon="pencil-square" aria-label="Edit category" wire:click="openEdit({{ $category->id }})" />
                                @endcan
                                @can('delete', $category)
                                    <flux:button size="sm" variant="ghost" icon="trash" aria-label="Delete category" wire:click="confirmDelete({{ $category->id }})" />
                                @endcan
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7" class="py-10 text-center text-zinc-500 dark:text-zinc-400">
                            No categories match your search.
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <flux:modal wire:model="showForm" class="md:w-[34rem]">
        <form wire:submit="save" class="space-y-4">
            <div>
                <flux:heading size="lg">{{ $editingId ? 'Edit category' : 'New category' }}</flux:heading>
                <flux:subheading>Categories nest up to five levels. Codes are immutable.</flux:subheading>
            </div>

            <flux:select wire:model="form.parent_id" label="Parent category">
                <option value="">Top-level category</option>
                @foreach ($parents as $parent)
                    <option value="{{ $parent->id }}">{{ $parent->code }} · {{ $parent->name }}</option>
                @endforeach
            </flux:select>

            <flux:input wire:model="form.code" label="Code" placeholder="e.g. CAT-CABLE" :disabled="$editingId !== null" required />
            <flux:input wire:model="form.name" label="Name" placeholder="e.g. Cables & Conductors" required />
            <flux:textarea wire:model="form.description" label="Description" rows="2" />

            <div class="grid gap-3 sm:grid-cols-2">
                <flux:switch wire:model="form.track_batches" label="Track batches" />
                <flux:switch wire:model="form.track_serials" label="Track serials" />
            </div>
            <flux:switch wire:model="form.requires_quality_inspection" label="Requires quality inspection" />
            <flux:switch wire:model="form.is_active" label="Active" />

            <div class="flex justify-end gap-2 pt-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showForm', false)">Cancel</flux:button>
                <flux:button type="submit" variant="primary">{{ $editingId ? 'Save changes' : 'Create category' }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model="showDeleteModal" class="md:w-96">
        <div class="space-y-4">
            <div>
                <flux:heading size="lg">Delete category</flux:heading>
                <flux:subheading>Categories with items or subcategories cannot be deleted.</flux:subheading>
            </div>
            <p class="text-sm text-zinc-600 dark:text-zinc-300">
                Delete <span class="font-semibold">{{ $deletingName }}</span>?
            </p>
            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showDeleteModal', false)">Cancel</flux:button>
                <flux:button type="button" variant="danger" wire:click="delete">Delete category</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
