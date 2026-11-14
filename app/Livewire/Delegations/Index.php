<?php

namespace App\Livewire\Delegations;

use App\Domain\Workflow\Models\ApprovalDelegation;
use App\Domain\Workflow\Models\ApprovalFlow;
use App\Domain\Workflow\Services\ApprovalDelegationService;
use App\Enums\UserStatus;
use App\Livewire\Concerns\HandlesDomainActions;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Delegations')]
class Index extends Component
{
    use HandlesDomainActions, WithPagination;

    public string $search = '';

    public string $statusFilter = '';

    public bool $showForm = false;

    public ?int $delegateId = null;

    public string $documentType = '';

    public string $startsAt = '';

    public string $endsAt = '';

    public string $reason = '';

    public function mount(): void
    {
        $this->authorize('viewAny', ApprovalDelegation::class);
        $this->resetForm();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function resetForm(): void
    {
        $this->delegateId = null;
        $this->documentType = '';
        $this->startsAt = now()->format('Y-m-d');
        $this->endsAt = now()->addDays(7)->format('Y-m-d');
        $this->reason = '';
        $this->resetValidation();
    }

    public function openCreate(): void
    {
        $this->authorize('create', ApprovalDelegation::class);
        $this->resetForm();
        $this->showForm = true;
    }

    public function save(ApprovalDelegationService $service): void
    {
        $this->authorize('create', ApprovalDelegation::class);

        $this->validate([
            'delegateId' => ['required', 'integer', 'exists:users,id'],
            'documentType' => ['nullable', 'string', 'exists:approval_flows,document_type'],
            'startsAt' => ['required', 'date'],
            'endsAt' => ['required', 'date', 'after_or_equal:startsAt'],
            'reason' => ['nullable', 'string', 'max:500'],
        ], [
            'endsAt.after_or_equal' => 'The delegation end date cannot be before its start date.',
        ]);

        $delegate = User::findOrFail($this->delegateId);

        if ($delegate->id === auth()->id()) {
            $this->addError('delegateId', 'You cannot delegate approval authority to yourself.');

            return;
        }

        $created = $this->runDomainAction(
            fn () => $service->create(
                auth()->user(),
                $delegate,
                Carbon::parse($this->startsAt)->startOfDay(),
                Carbon::parse($this->endsAt)->endOfDay(),
                $this->documentType !== '' ? $this->documentType : null,
                trim($this->reason) !== '' ? trim($this->reason) : null,
                auth()->user(),
            ),
            'Approval authority delegated.',
        );

        if ($created) {
            $this->showForm = false;
            $this->resetForm();
            $this->resetPage();
        }
    }

    public function cancelDelegation(int $delegationId, ApprovalDelegationService $service): void
    {
        $delegation = ApprovalDelegation::findOrFail($delegationId);
        $this->authorize('delete', $delegation);

        $this->runDomainAction(
            fn () => $service->cancel($delegation, auth()->user()),
            'Delegation cancelled.',
        );
    }

    public function render()
    {
        $user = auth()->user();
        $canSeeAll = $user->can(Permissions::WORKFLOW_OVERRIDE);

        $delegations = ApprovalDelegation::query()
            ->with(['delegator', 'delegate', 'createdBy'])
            ->when(! $canSeeAll, function (Builder $query) use ($user): void {
                $query->where(function (Builder $query) use ($user): void {
                    $query->where('delegator_id', $user->id)
                        ->orWhere('delegate_id', $user->id);
                });
            })
            ->when($this->search !== '', function (Builder $query): void {
                $term = '%'.trim($this->search).'%';

                $query->where(function (Builder $query) use ($term): void {
                    $query->whereHas('delegator', fn (Builder $query) => $query->where('name', 'like', $term))
                        ->orWhereHas('delegate', fn (Builder $query) => $query->where('name', 'like', $term))
                        ->orWhere('document_type', 'like', $term);
                });
            })
            ->when($this->statusFilter === 'active', fn (Builder $query) => $query
                ->where('is_active', true)
                ->where('starts_at', '<=', now())
                ->where('ends_at', '>=', now()))
            ->when($this->statusFilter === 'scheduled', fn (Builder $query) => $query
                ->where('is_active', true)
                ->where('starts_at', '>', now()))
            ->when($this->statusFilter === 'ended', fn (Builder $query) => $query
                ->where(fn (Builder $query) => $query->where('is_active', false)->orWhere('ends_at', '<', now())))
            ->orderByDesc('starts_at')
            ->paginate(15);

        $delegates = User::query()
            ->where('status', UserStatus::Active->value)
            ->whereKeyNot($user->id)
            ->permission(Permissions::WORKFLOW_ACT)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('livewire.delegations.index', [
            'delegations' => $delegations,
            'delegates' => $delegates,
            'documentTypes' => ApprovalFlow::query()->orderBy('document_type')->pluck('document_type'),
            'canSeeAll' => $canSeeAll,
            'canCreate' => $user->can('create', ApprovalDelegation::class),
        ]);
    }
}
