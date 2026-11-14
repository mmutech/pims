<?php

namespace App\Livewire\Administration\Sequences;

use App\Domain\Administration\Models\DocumentSequence;
use App\Domain\Administration\Services\DocumentSequenceService;
use App\Enums\DocumentType;
use App\Livewire\Concerns\HandlesDomainActions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Document Sequences')]
class Index extends Component
{
    use HandlesDomainActions, WithPagination;

    public string $search = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    /** @var array<string, mixed> */
    public array $form = [];

    public function mount(): void
    {
        $this->authorize('viewAny', DocumentSequence::class);
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
            'next_number' => 1,
            'padding' => 5,
            'reset_policy' => 'yearly',
        ];
        $this->resetValidation();
    }

    public function openEdit(int $sequenceId): void
    {
        $sequence = DocumentSequence::findOrFail($sequenceId);
        $this->authorize('update', $sequence);

        $this->editingId = $sequence->id;
        $this->form = [
            'next_number' => $sequence->next_number,
            'padding' => $sequence->padding,
            'reset_policy' => $sequence->reset_policy,
        ];
        $this->resetValidation();
        $this->showForm = true;
    }

    public function save(DocumentSequenceService $service): void
    {
        $sequence = DocumentSequence::findOrFail($this->editingId);
        $this->authorize('update', $sequence);

        $this->validate([
            'form.next_number' => ['required', 'integer', 'min:1'],
            'form.padding' => ['required', 'integer', 'min:1', 'max:10'],
            'form.reset_policy' => ['required', Rule::in(['yearly', 'never'])],
        ]);

        $saved = $this->runDomainAction(
            fn () => $service->update(
                $sequence,
                (int) $this->form['next_number'],
                (int) $this->form['padding'],
                $this->form['reset_policy'],
            ),
            'Document sequence updated.',
        );

        if ($saved) {
            $this->showForm = false;
            $this->resetForm();
        }
    }

    public function render()
    {
        $sequences = DocumentSequence::query()
            ->when($this->search !== '', function (Builder $query): void {
                $term = '%'.$this->search.'%';

                $query->where(function (Builder $query) use ($term): void {
                    $query->where('prefix', 'like', $term)
                        ->orWhere('document_type', 'like', $term)
                        ->orWhere('year', 'like', $term);
                });
            })
            ->orderBy('document_type')
            ->orderByDesc('year')
            ->paginate(20);

        $editing = $this->editingId !== null ? DocumentSequence::find($this->editingId) : null;

        return view('livewire.administration.sequences.index', [
            'sequences' => $sequences,
            'editing' => $editing,
            'documentTypes' => collect(DocumentType::cases())->mapWithKeys(
                fn (DocumentType $type): array => [$type->value => $type->label()],
            ),
            'resetPolicies' => [
                'yearly' => 'Reset every calendar year',
                'never' => 'Continuous numbering',
            ],
        ]);
    }
}
