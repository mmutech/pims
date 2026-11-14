<?php

namespace App\Livewire\Organization\Projects;

use App\Domain\Organization\Models\CostCenter;
use App\Domain\Organization\Models\Department;
use App\Domain\Organization\Models\Project;
use App\Domain\Organization\Services\OrganizationService;
use App\DTOs\Organization\ProjectData;
use App\Enums\ProjectStatus;
use App\Livewire\Concerns\HandlesDomainActions;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Projects')]
class Index extends Component
{
    use HandlesDomainActions, WithPagination;

    public string $search = '';

    public string $statusFilter = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    /** @var array<string, mixed> */
    public array $form = [];

    public bool $showDeleteModal = false;

    public ?int $deletingId = null;

    public string $deletingName = '';

    public bool $showStatusModal = false;

    public ?int $statusProjectId = null;

    public string $statusProjectName = '';

    public string $newStatus = '';

    /** @var list<string> */
    public array $allowedStatuses = [];

    public bool $showMembersModal = false;

    public ?int $membersProjectId = null;

    public string $membersProjectName = '';

    public string $memberUserId = '';

    public string $memberRole = 'member';

    public function mount(): void
    {
        $this->authorize('viewAny', Project::class);
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
        $this->editingId = null;
        $this->form = [
            'department_id' => '',
            'cost_center_id' => '',
            'manager_id' => '',
            'code' => '',
            'name' => '',
            'description' => '',
            'start_date' => '',
            'end_date' => '',
            'status' => ProjectStatus::Planned->value,
            'budget_amount' => '0.00',
            'currency' => 'NGN',
            'is_active' => true,
        ];
        $this->resetValidation();
    }

    public function openCreate(): void
    {
        $this->authorize('create', Project::class);
        $this->resetForm();
        $this->showForm = true;
    }

    public function openEdit(int $projectId): void
    {
        $project = Project::findOrFail($projectId);
        $this->authorize('update', $project);

        $this->editingId = $project->id;
        $this->form = [
            'department_id' => $project->department_id !== null ? (string) $project->department_id : '',
            'cost_center_id' => $project->cost_center_id !== null ? (string) $project->cost_center_id : '',
            'manager_id' => $project->manager_id !== null ? (string) $project->manager_id : '',
            'code' => $project->code,
            'name' => $project->name,
            'description' => (string) $project->description,
            'start_date' => $project->start_date?->toDateString() ?? '',
            'end_date' => $project->end_date?->toDateString() ?? '',
            'status' => $project->status->value,
            'budget_amount' => (string) $project->budget_amount,
            'currency' => $project->currency,
            'is_active' => $project->is_active,
        ];
        $this->resetValidation();
        $this->showForm = true;
    }

    public function save(OrganizationService $service): void
    {
        $project = $this->editingId !== null ? Project::findOrFail($this->editingId) : null;

        $project !== null
            ? $this->authorize('update', $project)
            : $this->authorize('create', Project::class);

        $this->validate([
            'form.department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')],
            'form.cost_center_id' => ['nullable', 'integer', Rule::exists('cost_centers', 'id')],
            'form.manager_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'form.code' => ['required', 'string', 'max:30', Rule::unique('projects', 'code')->ignore($project?->id)],
            'form.name' => ['required', 'string', 'max:200'],
            'form.description' => ['nullable', 'string', 'max:5000'],
            'form.start_date' => ['nullable', 'date'],
            'form.end_date' => ['nullable', 'date', 'after_or_equal:form.start_date'],
            'form.status' => ['required', Rule::enum(ProjectStatus::class)],
            'form.budget_amount' => ['required', 'numeric', 'min:0'],
            'form.currency' => ['required', 'string', 'size:3'],
            'form.is_active' => ['boolean'],
        ]);

        $payload = $this->form;
        $payload['department_id'] = $this->form['department_id'] !== '' ? $this->form['department_id'] : null;
        $payload['cost_center_id'] = $this->form['cost_center_id'] !== '' ? $this->form['cost_center_id'] : null;
        $payload['manager_id'] = $this->form['manager_id'] !== '' ? $this->form['manager_id'] : null;
        $payload['start_date'] = $this->form['start_date'] !== '' ? $this->form['start_date'] : null;
        $payload['end_date'] = $this->form['end_date'] !== '' ? $this->form['end_date'] : null;

        $data = ProjectData::fromArray($payload);

        $saved = $this->runDomainAction(
            fn () => $project !== null ? $service->updateProject($project, $data) : $service->createProject($data),
            $project !== null ? 'Project updated.' : 'Project created.',
        );

        if ($saved) {
            $this->showForm = false;
            $this->resetForm();
        }
    }

    public function confirmDelete(int $projectId): void
    {
        $project = Project::findOrFail($projectId);
        $this->authorize('delete', $project);

        $this->deletingId = $project->id;
        $this->deletingName = $project->name;
        $this->showDeleteModal = true;
    }

    public function delete(OrganizationService $service): void
    {
        $project = Project::findOrFail($this->deletingId);
        $this->authorize('delete', $project);

        if ($this->runDomainAction(fn () => $service->deleteProject($project), 'Project deleted.')) {
            $this->showDeleteModal = false;
            $this->deletingId = null;
            $this->deletingName = '';
            $this->resetPage();
        }
    }

    public function openStatusModal(int $projectId): void
    {
        $project = Project::findOrFail($projectId);
        $this->authorize('update', $project);

        $this->statusProjectId = $project->id;
        $this->statusProjectName = $project->name;
        $this->allowedStatuses = array_map(
            fn (ProjectStatus $status): string => $status->value,
            $project->status->allowedTransitions(),
        );
        $this->newStatus = $this->allowedStatuses[0] ?? '';
        $this->showStatusModal = true;
    }

    public function changeStatus(OrganizationService $service): void
    {
        $project = Project::findOrFail($this->statusProjectId);
        $this->authorize('update', $project);

        $this->validate([
            'newStatus' => ['required', Rule::enum(ProjectStatus::class)],
        ]);

        $status = ProjectStatus::from($this->newStatus);

        if ($this->runDomainAction(fn () => $service->transitionProject($project, $status), "Project moved to {$status->label()}.")) {
            $this->showStatusModal = false;
            $this->statusProjectId = null;
            $this->statusProjectName = '';
            $this->newStatus = '';
            $this->allowedStatuses = [];
        }
    }

    public function openMembersModal(int $projectId): void
    {
        $project = Project::findOrFail($projectId);
        $this->authorize('manageMembers', $project);

        $this->membersProjectId = $project->id;
        $this->membersProjectName = $project->name;
        $this->memberUserId = '';
        $this->memberRole = 'member';
        $this->showMembersModal = true;
    }

    public function addMember(OrganizationService $service): void
    {
        $project = Project::findOrFail($this->membersProjectId);
        $this->authorize('manageMembers', $project);

        $this->validate([
            'memberUserId' => ['required', 'integer', Rule::exists('users', 'id')],
            'memberRole' => ['required', 'string', 'max:50'],
        ]);

        $user = User::findOrFail((int) $this->memberUserId);

        if ($this->runDomainAction(fn () => $service->addMember($project, $user, $this->memberRole), 'Project member added.')) {
            $this->memberUserId = '';
            $this->memberRole = 'member';
        }
    }

    public function removeMember(int $memberId, OrganizationService $service): void
    {
        $project = Project::findOrFail($this->membersProjectId);
        $this->authorize('manageMembers', $project);

        $member = $project->members()->whereKey($memberId)->firstOrFail();
        $user = $member->user;

        if ($user !== null && $this->runDomainAction(fn () => $service->removeMember($project, $user), 'Project member removed.')) {
            //
        }
    }

    public function render()
    {
        $projects = $this->scopedQuery(Project::query())
            ->with(['department', 'costCenter', 'manager'])
            ->withCount(['members' => fn (Builder $query) => $query->where('is_active', true)])
            ->search($this->search)
            ->when($this->statusFilter !== '', fn (Builder $query) => $query->where('status', $this->statusFilter))
            ->orderBy('name')
            ->paginate(15);

        $members = $this->membersProjectId !== null
            ? Project::findOrFail($this->membersProjectId)->members()->with('user')->where('is_active', true)->get()
            : collect();

        return view('livewire.organization.projects.index', [
            'projects' => $projects,
            'departments' => Department::query()->orderBy('name')->get(),
            'costCenters' => CostCenter::query()->orderBy('name')->get(),
            'managers' => $this->managers(),
            'members' => $members,
            'statusOptions' => ProjectStatus::cases(),
        ]);
    }

    /**
     * @return Collection<int, User>
     */
    private function managers()
    {
        return User::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
    }

    private function scopedQuery(Builder $query): Builder
    {
        $user = auth()->user();

        if ($user->hasGlobalAccess()) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($user): void {
            $query->where('manager_id', $user->id)
                ->orWhereHas('members', fn (Builder $query) => $query
                    ->where('user_id', $user->id)
                    ->where('is_active', true));
        });
    }
}
