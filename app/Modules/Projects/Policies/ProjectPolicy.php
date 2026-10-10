<?php

declare(strict_types=1);

namespace App\Modules\Projects\Policies;

use App\Core\Permissions\Enums\PermissionScope;
use App\Models\User;
use App\Modules\Projects\Models\Project;

class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isActive() && $user->hasPermission('projects.view');
    }

    public function view(User $user, Project $project): bool
    {
        return ! ($project->is_system_default && $project->department_id !== null)
            && $this->hasScopedProjectAccess($user, $project, 'projects.view');
    }

    public function create(User $user): bool
    {
        if (! $user->isActive() || ! $user->hasPermission('projects.create')) {
            return false;
        }

        $scopes = $user->permissionScopes('projects.create');

        return in_array(PermissionScope::Organization->value, $scopes, true)
            || in_array(PermissionScope::Department->value, $scopes, true)
            || in_array(PermissionScope::DepartmentTree->value, $scopes, true);
    }

    public function update(User $user, Project $project): bool
    {
        return ! $project->is_system_default
            && $this->isProjectManager($user, $project)
            && $this->hasScopedProjectAccess($user, $project, 'projects.update');
    }

    public function delete(User $user, Project $project): bool
    {
        return ! $project->is_system_default
            && $this->isProjectManager($user, $project)
            && $this->hasScopedProjectAccess($user, $project, 'projects.delete');
    }

    public function manageRoles(User $user, Project $project): bool
    {
        return ! $project->is_system_default
            && $this->isProjectManager($user, $project)
            && $this->hasScopedProjectAccess($user, $project, 'projects.manage_roles');
    }

    public function manageMembers(User $user, Project $project): bool
    {
        return ! $project->is_system_default
            && $this->isProjectManager($user, $project)
            && $this->hasScopedProjectAccess($user, $project, 'projects.manage_members');
    }

    private function isProjectManager(User $user, Project $project): bool
    {
        return $user->isActive()
            && (int) $project->manager_id === (int) $user->id;
    }

    private function hasScopedProjectAccess(User $user, Project $project, string $permission): bool
    {
        if (! $user->isActive()) {
            return false;
        }

        $scopes = $user->permissionScopes($permission);

        if (in_array(PermissionScope::Organization->value, $scopes, true)) {
            return true;
        }

        if (
            in_array(PermissionScope::Self->value, $scopes, true)
            && (
                (int) $project->created_by === (int) $user->id
                || (int) $project->manager_id === (int) $user->id
            )
        ) {
            return true;
        }

        if (
            in_array(PermissionScope::Department->value, $scopes, true)
            && (
                ($project->department_id !== null && $user->departments()->whereKey($project->department_id)->exists())
                || ($project->is_system_default && $project->department_id === null
                    && $project->sections()->whereHas('department.members', fn ($query) => $query->where('users.id', $user->id))->exists())
            )
        ) {
            return true;
        }

        if (in_array(PermissionScope::DepartmentTree->value, $scopes, true) && $project->department_id !== null) {
            foreach ($user->departments()->get() as $department) {
                $ids = array_map(
                    'intval',
                    array_merge([$department->id], $department->descendantIds())
                );

                if (in_array((int) $project->department_id, $ids, true)) {
                    return true;
                }
            }
        }

        if (in_array(PermissionScope::DepartmentTree->value, $scopes, true) && $project->is_system_default && $project->department_id === null) {
            $treeDepartmentIds = [];
            foreach ($user->departments()->get() as $department) {
                $treeDepartmentIds[] = (int) $department->id;
                $treeDepartmentIds = array_merge($treeDepartmentIds, $department->descendantIds());
            }
            if ($treeDepartmentIds !== [] && $project->sections()->whereIn('department_id', array_values(array_unique($treeDepartmentIds)))->exists()) {
                return true;
            }
        }

        return in_array(PermissionScope::Project->value, $scopes, true)
            && (
                (int) $project->manager_id === (int) $user->id
                || $project->users()->whereKey($user->id)->exists()
                || ($project->is_system_default && $project->department_id === null
                    && $project->sections()->whereHas('department.members', fn ($query) => $query->where('users.id', $user->id))->exists())
            );
    }
}
