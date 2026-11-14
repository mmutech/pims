<?php

namespace App\Livewire\Approvals;

use App\Domain\Workflow\Models\ApprovalDelegation;
use App\Domain\Workflow\Models\ApprovalRequest;
use App\Enums\ApprovalStatus;
use App\Livewire\Concerns\HandlesDomainActions;
use App\Support\Permissions;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Approval History')]
class History extends Component
{
    use HandlesDomainActions, WithPagination;

    public string $search = '';

    public string $statusFilter = '';

    public string $documentTypeFilter = '';

    public function mount(): void
    {
        $this->authorize('viewAny', ApprovalRequest::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedDocumentTypeFilter(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->statusFilter = '';
        $this->documentTypeFilter = '';
        $this->resetPage();
    }

    public function render()
    {
        $user = auth()->user();
        $canSeeAll = $user->can(Permissions::WORKFLOW_OVERRIDE) || $user->can(Permissions::AUDIT_VIEW);

        $query = ApprovalRequest::query()
            ->with(['flow', 'requester', 'stages.assignees.user'])
            ->search($this->search)
            ->when($this->statusFilter !== '', fn (Builder $query) => $query->where('status', $this->statusFilter))
            ->when($this->documentTypeFilter !== '', fn (Builder $query) => $query->where('document_type', $this->documentTypeFilter));

        if (! $canSeeAll) {
            $delegations = ApprovalDelegation::query()
                ->active()
                ->where('delegate_id', $user->id)
                ->get();

            $query->where(function (Builder $query) use ($user, $delegations): void {
                $query->where('requested_by', $user->id)
                    ->orWhereHas('stages.assignees', fn (Builder $query) => $query->where('user_id', $user->id));

                foreach ($delegations as $delegation) {
                    $query->orWhere(function (Builder $query) use ($delegation): void {
                        if ($delegation->document_type !== null) {
                            $query->where('document_type', $delegation->document_type);
                        }

                        $query->whereHas(
                            'stages.assignees',
                            fn (Builder $query) => $query->where('user_id', $delegation->delegator_id),
                        );
                    });
                }
            });
        }

        return view('livewire.approvals.history', [
            'requests' => $query->orderByDesc('requested_at')->orderByDesc('id')->paginate(15),
            'statusOptions' => ApprovalStatus::cases(),
            'documentTypes' => ApprovalRequest::query()
                ->distinct()
                ->orderBy('document_type')
                ->pluck('document_type'),
            'canSeeAll' => $canSeeAll,
            'statusColors' => [
                ApprovalStatus::Pending->value => 'amber',
                ApprovalStatus::Approved->value => 'green',
                ApprovalStatus::Rejected->value => 'red',
                ApprovalStatus::Returned->value => 'orange',
                ApprovalStatus::Cancelled->value => 'zinc',
                ApprovalStatus::Expired->value => 'zinc',
            ],
        ]);
    }
}
