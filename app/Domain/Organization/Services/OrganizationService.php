<?php

namespace App\Domain\Organization\Services;

use App\Domain\Organization\Models\BusinessUnit;
use App\Domain\Organization\Models\CostCenter;
use App\Domain\Organization\Models\Department;
use App\Domain\Organization\Models\Project;
use App\DTOs\Organization\BusinessUnitData;
use App\DTOs\Organization\CostCenterData;
use App\DTOs\Organization\DepartmentData;
use App\DTOs\Organization\ProjectData;
use App\Enums\ProjectStatus;
use App\Models\User;
use App\Support\Decimal;
use DomainException;
use Illuminate\Support\Facades\DB;

class OrganizationService
{
    public function createBusinessUnit(BusinessUnitData $data): BusinessUnit
    {
        return BusinessUnit::create($data->toArray());
    }

    public function updateBusinessUnit(BusinessUnit $businessUnit, BusinessUnitData $data): BusinessUnit
    {
        $this->assertCodeUnchanged($businessUnit->code, $data->code, 'business unit');

        if (! $data->isActive && $businessUnit->hasActiveDependants()) {
            throw new DomainException('Business unit cannot be deactivated while active dependants exist.');
        }

        $businessUnit->update($data->toArray());

        return $businessUnit->refresh();
    }

    public function deleteBusinessUnit(BusinessUnit $businessUnit): void
    {
        if ($businessUnit->warehouses()->exists()) {
            throw new DomainException('Business unit cannot be deleted while warehouses exist.');
        }

        $businessUnit->delete();
    }

    public function createDepartment(DepartmentData $data): Department
    {
        return Department::create($data->toArray());
    }

    public function updateDepartment(Department $department, DepartmentData $data): Department
    {
        $this->assertCodeUnchanged($department->code, $data->code, 'department');

        if (! $data->isActive && $department->hasActiveDependants()) {
            throw new DomainException('Department cannot be deactivated while active cost centres or projects exist.');
        }

        $department->update($data->toArray());

        return $department->refresh();
    }

    public function deleteDepartment(Department $department): void
    {
        if ($department->costCenters()->exists() || $department->projects()->exists()) {
            throw new DomainException('Department cannot be deleted while dependants exist.');
        }

        $department->delete();
    }

    public function createCostCenter(CostCenterData $data): CostCenter
    {
        return CostCenter::create($data->toArray());
    }

    public function updateCostCenter(CostCenter $costCenter, CostCenterData $data): CostCenter
    {
        $this->assertCodeUnchanged($costCenter->code, $data->code, 'cost centre');

        if (! $data->isActive && $costCenter->projects()->where('is_active', true)->exists()) {
            throw new DomainException('Cost centre cannot be deactivated while active projects reference it.');
        }

        $costCenter->update($data->toArray());

        return $costCenter->refresh();
    }

    public function deleteCostCenter(CostCenter $costCenter): void
    {
        if ($costCenter->projects()->exists()) {
            throw new DomainException('Cost centre cannot be deleted while projects reference it.');
        }

        $costCenter->delete();
    }

    public function createProject(ProjectData $data): Project
    {
        $this->validateProject($data);

        return DB::transaction(function () use ($data): Project {
            $project = Project::create($data->toArray());

            if ($data->managerId !== null) {
                $this->addMember($project, User::findOrFail($data->managerId), 'manager');
            }

            return $project;
        });
    }

    public function updateProject(Project $project, ProjectData $data): Project
    {
        $this->assertCodeUnchanged($project->code, $data->code, 'project');
        $this->validateProject($data);

        if ($project->status->isTerminal() && $data->status !== $project->status) {
            throw new DomainException('Completed or cancelled projects cannot change status.');
        }

        if (! $project->status->canTransitionTo($data->status) && $project->status !== $data->status) {
            throw new DomainException("Invalid project status transition from {$project->status->value} to {$data->status->value}.");
        }

        $project->update($data->toArray());

        return $project->refresh();
    }

    public function deleteProject(Project $project): void
    {
        if ($project->status === ProjectStatus::Active) {
            throw new DomainException('Active projects cannot be deleted; complete or cancel the project first.');
        }

        $project->delete();
    }

    public function transitionProject(Project $project, ProjectStatus $status): Project
    {
        if (! $project->status->canTransitionTo($status)) {
            throw new DomainException("Invalid project status transition from {$project->status->value} to {$status->value}.");
        }

        $project->update(['status' => $status]);

        return $project->refresh();
    }

    public function addMember(Project $project, User $user, string $role = 'member'): void
    {
        $project->members()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'role' => $role,
                'joined_at' => now()->toDateString(),
                'left_at' => null,
                'is_active' => true,
            ],
        );
    }

    public function removeMember(Project $project, User $user): void
    {
        $project->members()
            ->where('user_id', $user->id)
            ->update([
                'left_at' => now()->toDateString(),
                'is_active' => false,
            ]);
    }

    private function validateProject(ProjectData $data): void
    {
        if ($data->startDate !== null && $data->endDate !== null) {
            if (strtotime($data->endDate) < strtotime($data->startDate)) {
                throw new DomainException('Project end date must be on or after the start date.');
            }
        }

        if (Decimal::isNegative($data->budgetAmount, Decimal::MONEY_SCALE)) {
            throw new DomainException('Project budget cannot be negative.');
        }
    }

    private function assertCodeUnchanged(string $existing, string $incoming, string $label): void
    {
        if ($existing !== $incoming) {
            throw new DomainException("The {$label} code is immutable once created.");
        }
    }
}
