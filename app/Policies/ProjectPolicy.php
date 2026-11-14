<?php

namespace App\Policies;

use App\Domain\Organization\Models\Project;
use App\Models\User;
use App\Support\Permissions;

class ProjectPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isSuperAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::ORG_PROJECTS_VIEW);
    }

    public function view(User $user, Project $project): bool
    {
        return $user->can(Permissions::ORG_PROJECTS_VIEW) && $user->canAccessProject($project);
    }

    public function create(User $user): bool
    {
        return $user->can(Permissions::ORG_PROJECTS_CREATE);
    }

    public function update(User $user, Project $project): bool
    {
        return $user->can(Permissions::ORG_PROJECTS_UPDATE) && $user->canAccessProject($project);
    }

    public function delete(User $user, Project $project): bool
    {
        return $user->can(Permissions::ORG_PROJECTS_DELETE) && $user->canAccessProject($project);
    }

    public function manageMembers(User $user, Project $project): bool
    {
        return $user->can(Permissions::ORG_PROJECT_MEMBERS_MANAGE) && $user->canAccessProject($project);
    }
}
