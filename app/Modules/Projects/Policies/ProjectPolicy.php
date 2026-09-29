<?php

declare(strict_types=1);

namespace App\Modules\Projects\Policies;

use App\Models\User;
use App\Modules\Projects\Models\Project;

class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isActive()
            && $user->hasPermission('projects.view');
    }

    public function view(User $user, Project $project): bool
    {
        return $user->isActive()
            && $user->hasPermission('projects.view');
    }

    public function create(User $user): bool
    {
        return $user->isActive()
            && $user->hasPermission('projects.create');
    }

    public function update(User $user, Project $project): bool
    {
        return $user->isActive()
            && $user->hasPermission('projects.update')
            && $project->manager_id === $user->id;
    }

    public function delete(User $user, Project $project): bool
    {
        return $user->isActive()
            && $user->hasPermission('projects.delete')
            && $project->manager_id === $user->id;
    }

    public function manageRoles(User $user, Project $project): bool
    {
        return $user->isActive()
            && $user->hasPermission('projects.manage_roles')
            && $project->manager_id === $user->id;
    }

    public function manageMembers(User $user, Project $project): bool
    {
        return $user->isActive()
            && $user->hasPermission('projects.manage_members')
            && $project->manager_id === $user->id;
    }
}