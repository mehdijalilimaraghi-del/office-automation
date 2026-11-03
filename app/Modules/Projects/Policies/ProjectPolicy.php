<?php

declare(strict_types=1);

namespace App\Modules\Projects\Policies;

use App\Core\Permissions\Enums\PermissionScope;
use App\Core\Settings\Services\OrganizationDepartmentRoleService;
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
        return ! ($project->is_system_default && $project->department_id !== null && ! $project->is_active)
            && $this->hasScopedProjectAccess($user, $project, 'projects.view');
    }

    public function create(User $user): bool
    {
        if (! $this->canManageProjects($user) || ! $user->hasPermission('projects.create')) {
            return false;
        }

        return $user->permissionScopes('projects.create') !== [];
    }

    public function update(User $user, Project $project): bool
    {
        if (! $this->canManageProjects($user)) {
            return false;
        }

        if ($user->isSystemAdmin()) {
            return true;
        }

        // Project administration belongs to the Project Management unit, not
        // to the selected operational project manager or legacy project members.
        return $user->hasPermission('projects.update')
            && $user->permissionScopes('projects.update') !== [];
    }

    public function delete(User $user, Project $project): bool
    {
        if ($project->is_system_default || ! $this->canManageProjects($user)) {
            return false;
        }

        if ($user->isSystemAdmin()) {
            return true;
        }

        // Authorized Project Management staff administer project records centrally.
        return $user->hasPermission('projects.delete')
            && $user->permissionScopes('projects.delete') !== [];
    }

    public function manageRoles(User $user, Project $project): bool
    {
        return ! $project->is_system_default
            && $this->canManageProjects($user)
            && $this->isProjectManager($user, $project)
            && $user->hasPermission('projects.manage_roles')
            && $this->hasScopedProjectAccess($user, $project, 'projects.manage_roles');
    }

    public function manageMembers(User $user, Project $project): bool
    {
        return ! $project->is_system_default
            && $this->canManageProjects($user)
            && $this->isProjectManager($user, $project)
            && $user->hasPermission('projects.manage_members')
            && $this->hasScopedProjectAccess($user, $project, 'projects.manage_members');
    }

    /**
     * Project administration is limited to active members of the
     * configured project-management department under the configured management department. System administrators
     * retain the existing emergency-administration bypass.
     */
    private function canManageProjects(User $user): bool
    {
        if (! $user->isActive()) {
            return false;
        }

        if ($user->isSystemAdmin()) {
            return true;
        }

        return app(OrganizationDepartmentRoleService::class)
            ->userBelongsTo($user, OrganizationDepartmentRoleService::PROJECT_MANAGEMENT);
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
                || $project->tasks()->where(function ($taskQuery) use ($user): void {
                    $taskQuery->where('tasks.assignee_id', $user->id)
                        ->orWhere('tasks.created_by', $user->id);
                })->exists()
            )
        ) {
            return true;
        }

        $departmentIds = $user->departments()
            ->pluck('departments.id')
            ->merge($user->managedDepartments()->pluck('departments.id'))
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        if (
            in_array(PermissionScope::Department->value, $scopes, true)
            && (
                ($project->department_id !== null && $departmentIds->contains((int) $project->department_id))
                || $project->sections()->whereIn('department_id', $departmentIds)->exists()
            )
        ) {
            return true;
        }

        if (in_array(PermissionScope::DepartmentTree->value, $scopes, true)) {
            $responsibleDepartments = $user->departments()
                ->get()
                ->merge($user->managedDepartments()->get())
                ->unique('id');

            foreach ($responsibleDepartments as $department) {
                $ids = array_map(
                    'intval',
                    array_merge([$department->id], $department->descendantIds())
                );

                if (($project->department_id !== null && in_array((int) $project->department_id, $ids, true)) || $project->sections()->whereIn('department_id', $ids)->exists()) {
                    return true;
                }
            }
        }

        if (in_array(PermissionScope::DepartmentTree->value, $scopes, true) && $project->is_system_default && $project->department_id === null) {
            $treeDepartmentIds = [];
            $responsibleDepartments = $user->departments()
                ->get()
                ->merge($user->managedDepartments()->get())
                ->unique('id');

            foreach ($responsibleDepartments as $department) {
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
                || $project->tasks()->where(function ($taskQuery) use ($user): void {
                    $taskQuery->where('tasks.assignee_id', $user->id)
                        ->orWhere('tasks.created_by', $user->id);
                })->exists()
                || ($project->is_system_default && $project->department_id === null
                    && $project->sections()->whereHas('department.members', fn ($query) => $query->where('users.id', $user->id))->exists())
            );
    }
}
