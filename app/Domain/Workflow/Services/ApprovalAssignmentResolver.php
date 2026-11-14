<?php

namespace App\Domain\Workflow\Services;

use App\Domain\Organization\Models\Project;
use App\Domain\Warehouse\Models\Warehouse;
use App\Domain\Workflow\Models\ApprovalAssignment;
use App\Enums\ApprovalAssigneeType;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Support\Collection;

final class ApprovalAssignmentResolver
{
    /**
     * Resolve the active users who may act on a stage.
     *
     * @param  Collection<int, ApprovalAssignment>  $assignments
     * @param  array<string, mixed>  $attributes
     * @param  list<int>  $excludedUserIds
     * @return Collection<int, User>
     */
    public function resolve(Collection $assignments, array $attributes, array $excludedUserIds = []): Collection
    {
        $users = new Collection;

        foreach ($assignments as $assignment) {
            foreach ($this->resolveAssignment($assignment, $attributes) as $user) {
                if (in_array($user->id, $excludedUserIds, true)) {
                    continue;
                }

                if (! $user->isActive()) {
                    continue;
                }

                $users->put($user->id, $user);
            }
        }

        return $users->values();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return Collection<int, User>
     */
    private function resolveAssignment(ApprovalAssignment $assignment, array $attributes): Collection
    {
        return match ($assignment->assignee_type) {
            ApprovalAssigneeType::Role => $this->resolveRole($assignment, $attributes),
            ApprovalAssigneeType::User => $this->resolveUser($assignment),
            ApprovalAssigneeType::ProjectManager => $this->resolveProjectManager($attributes),
            ApprovalAssigneeType::WarehouseManager => $this->resolveWarehouseManager($attributes),
            ApprovalAssigneeType::DepartmentManager, ApprovalAssigneeType::RequesterManager => new Collection,
        };
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return Collection<int, User>
     */
    private function resolveRole(ApprovalAssignment $assignment, array $attributes): Collection
    {
        $role = $assignment->role;

        if ($role === null) {
            return new Collection;
        }

        return User::query()
            ->where('status', UserStatus::Active->value)
            ->role($role->name)
            ->get()
            ->filter(fn (User $user) => $this->isInDocumentScope($user, $attributes))
            ->values();
    }

    /**
     * @return Collection<int, User>
     */
    private function resolveUser(ApprovalAssignment $assignment): Collection
    {
        $user = $assignment->user;

        if ($user === null) {
            return new Collection;
        }

        return new Collection([$user]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return Collection<int, User>
     */
    private function resolveProjectManager(array $attributes): Collection
    {
        $projectId = $attributes['project_id'] ?? null;

        if ($projectId === null) {
            return new Collection;
        }

        $manager = Project::query()->with('manager')->find($projectId)?->manager;

        return $manager !== null ? new Collection([$manager]) : new Collection;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return Collection<int, User>
     */
    private function resolveWarehouseManager(array $attributes): Collection
    {
        $warehouseId = $attributes['warehouse_id'] ?? null;

        if ($warehouseId === null) {
            return new Collection;
        }

        $manager = Warehouse::query()->with('manager')->find($warehouseId)?->manager;

        return $manager !== null ? new Collection([$manager]) : new Collection;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function isInDocumentScope(User $user, array $attributes): bool
    {
        $warehouseId = $attributes['warehouse_id'] ?? null;

        if ($warehouseId === null) {
            return true;
        }

        if ($user->hasGlobalAccess()) {
            return true;
        }

        $warehouse = Warehouse::query()->find($warehouseId);

        return $warehouse !== null && $user->hasWarehouseAccess($warehouse);
    }
}
