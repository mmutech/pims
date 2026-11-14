<?php

namespace App\Livewire\Workflows;

use App\Domain\Workflow\Models\ApprovalFlow;
use App\Domain\Workflow\Services\ApprovalFlowService;
use App\Livewire\Concerns\HandlesDomainActions;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Approval Workflows')]
class Index extends Component
{
    use HandlesDomainActions, WithPagination;

    public string $search = '';

    public string $statusFilter = '';

    public bool $showDeleteModal = false;

    public ?int $deletingId = null;

    public string $deletingName = '';

    public function mount(): void
    {
        $this->authorize('viewAny', ApprovalFlow::class);
        $this->feedback = session('status');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function activate(int $flowId, ApprovalFlowService $service): void
    {
        $flow = ApprovalFlow::findOrFail($flowId);
        $this->authorize('update', $flow);

        $this->runDomainAction(
            fn () => $service->activate($flow),
            'Approval flow activated.',
        );
    }

    public function deactivate(int $flowId, ApprovalFlowService $service): void
    {
        $flow = ApprovalFlow::findOrFail($flowId);
        $this->authorize('update', $flow);

        $this->runDomainAction(
            fn () => $service->deactivate($flow),
            'Approval flow deactivated.',
        );
    }

    public function confirmDelete(int $flowId): void
    {
        $flow = ApprovalFlow::findOrFail($flowId);
        $this->authorize('delete', $flow);

        $this->deletingId = $flow->id;
        $this->deletingName = $flow->name;
        $this->resetValidation();
        $this->showDeleteModal = true;
    }

    public function delete(ApprovalFlowService $service): void
    {
        $flow = ApprovalFlow::findOrFail($this->deletingId);
        $this->authorize('delete', $flow);

        if ($this->runDomainAction(fn () => $service->delete($flow), 'Approval flow deleted.')) {
            $this->showDeleteModal = false;
            $this->deletingId = null;
            $this->deletingName = '';
            $this->resetPage();
        }
    }

    public function render()
    {
        $flows = ApprovalFlow::query()
            ->withCount(['stages', 'requests'])
            ->search($this->search)
            ->when($this->statusFilter === 'active', fn (Builder $query) => $query->where('is_active', true))
            ->when($this->statusFilter === 'inactive', fn (Builder $query) => $query->where('is_active', false))
            ->orderBy('document_type')
            ->paginate(15);

        return view('livewire.workflows.index', [
            'flows' => $flows,
        ]);
    }
}
