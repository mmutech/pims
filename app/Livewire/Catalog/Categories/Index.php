<?php

namespace App\Livewire\Catalog\Categories;

use App\Domain\Catalog\Models\ItemCategory;
use App\Domain\Catalog\Services\CatalogService;
use App\DTOs\Catalog\CategoryData;
use App\Livewire\Concerns\HandlesDomainActions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Item Categories')]
class Index extends Component
{
    use HandlesDomainActions, WithPagination;

    public string $search = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    /** @var array<string, mixed> */
    public array $form = [];

    public bool $showDeleteModal = false;

    public ?int $deletingId = null;

    public string $deletingName = '';

    public function mount(): void
    {
        $this->authorize('viewAny', ItemCategory::class);
        $this->resetForm();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function resetForm(): void
    {
        $this->editingId = null;
        $this->form = [
            'parent_id' => '',
            'code' => '',
            'name' => '',
            'description' => '',
            'track_batches' => false,
            'track_serials' => false,
            'requires_quality_inspection' => true,
            'is_active' => true,
        ];
        $this->resetValidation();
    }

    public function openCreate(): void
    {
        $this->authorize('create', ItemCategory::class);
        $this->resetForm();
        $this->showForm = true;
    }

    public function openEdit(int $categoryId): void
    {
        $category = ItemCategory::findOrFail($categoryId);
        $this->authorize('update', $category);

        $this->editingId = $category->id;
        $this->form = [
            'parent_id' => $category->parent_id !== null ? (string) $category->parent_id : '',
            'code' => $category->code,
            'name' => $category->name,
            'description' => (string) $category->description,
            'track_batches' => $category->track_batches,
            'track_serials' => $category->track_serials,
            'requires_quality_inspection' => $category->requires_quality_inspection,
            'is_active' => $category->is_active,
        ];
        $this->resetValidation();
        $this->showForm = true;
    }

    public function save(CatalogService $service): void
    {
        $category = $this->editingId !== null ? ItemCategory::findOrFail($this->editingId) : null;

        $category !== null
            ? $this->authorize('update', $category)
            : $this->authorize('create', ItemCategory::class);

        $this->validate([
            'form.parent_id' => ['nullable', 'integer', Rule::exists('item_categories', 'id')],
            'form.code' => ['required', 'string', 'max:30', Rule::unique('item_categories', 'code')->ignore($category?->id)],
            'form.name' => ['required', 'string', 'max:150'],
            'form.description' => ['nullable', 'string', 'max:2000'],
            'form.track_batches' => ['boolean'],
            'form.track_serials' => ['boolean'],
            'form.requires_quality_inspection' => ['boolean'],
            'form.is_active' => ['boolean'],
        ]);

        $payload = $this->form;
        $payload['parent_id'] = $this->form['parent_id'] !== '' ? (int) $this->form['parent_id'] : null;

        $data = CategoryData::fromArray($payload);

        $saved = $this->runDomainAction(
            fn () => $category !== null ? $service->updateCategory($category, $data) : $service->createCategory($data),
            $category !== null ? 'Category updated.' : 'Category created.',
        );

        if ($saved) {
            $this->showForm = false;
            $this->resetForm();
        }
    }

    public function confirmDelete(int $categoryId): void
    {
        $category = ItemCategory::findOrFail($categoryId);
        $this->authorize('delete', $category);

        $this->deletingId = $category->id;
        $this->deletingName = $category->name;
        $this->showDeleteModal = true;
    }

    public function delete(CatalogService $service): void
    {
        $category = ItemCategory::findOrFail($this->deletingId);
        $this->authorize('delete', $category);

        if ($this->runDomainAction(fn () => $service->deleteCategory($category), 'Category deleted.')) {
            $this->showDeleteModal = false;
            $this->deletingId = null;
            $this->deletingName = '';
            $this->resetPage();
        }
    }

    public function render()
    {
        $categories = ItemCategory::query()
            ->with(['parent'])
            ->withCount(['children', 'items'])
            ->search($this->search)
            ->orderBy('name')
            ->paginate(15);

        $parents = ItemCategory::query()
            ->when($this->editingId !== null, fn (Builder $query) => $query->whereKeyNot($this->editingId))
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        return view('livewire.catalog.categories.index', [
            'categories' => $categories,
            'parents' => $parents,
        ]);
    }
}
