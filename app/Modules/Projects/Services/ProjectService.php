<?php

namespace App\Modules\Projects\Services;

use App\Models\User;
use App\Core\Permissions\Enums\PermissionScope;
use App\Modules\Projects\Actions\CreateProjectAction;
use App\Modules\Projects\Actions\DeleteProjectAction;
use App\Modules\Projects\Actions\UpdateProjectAction;
use App\Modules\Projects\Contracts\ProjectServiceContract;
use App\Modules\Projects\Models\Project;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ProjectService implements ProjectServiceContract
{
    public function __construct(
        private readonly CreateProjectAction $createProjectAction,
        private readonly UpdateProjectAction $updateProjectAction,
        private readonly DeleteProjectAction $deleteProjectAction,
    ) {
    }

    public function list(User $user): LengthAwarePaginator
    {
        $query = Project::query()
            ->where(function ($visibleProjects): void {
                $visibleProjects->where('is_system_default', false)->orWhere('is_active', true);
            })
            ->with(['manager', 'creator']);

        $scopes = $user->permissionScopes('projects.view');

        if (in_array(PermissionScope::Organization->value, $scopes, true)) {
            return $query->latest()->paginate(15)->withQueryString();
        }

        $query->where(function ($scopeQuery) use ($user, $scopes): void {
            if (in_array(PermissionScope::Self->value, $scopes, true)) {
                $scopeQuery
                    ->where('projects.created_by', $user->id)
                    ->orWhere('projects.manager_id', $user->id);
            }

            if (in_array(PermissionScope::Department->value, $scopes, true)) {
                $departmentIds = $user->departments()->pluck('departments.id');

                if ($departmentIds->isNotEmpty()) {
                    $scopeQuery->orWhereIn('projects.department_id', $departmentIds)
                        ->orWhereHas('sections', fn ($sectionQuery) => $sectionQuery->whereIn('department_id', $departmentIds));
                }
            }

            if (in_array(PermissionScope::DepartmentTree->value, $scopes, true)) {
                $departmentIds = [];

                foreach ($user->departments()->get() as $department) {
                    $departmentIds[] = (int) $department->id;
                    $departmentIds = array_merge($departmentIds, $department->descendantIds());
                }

                if ($departmentIds !== []) {
                    $scopeQuery->orWhereIn('projects.department_id', array_values(array_unique($departmentIds)));
                    $scopeQuery->orWhereHas('sections', fn ($sectionQuery) => $sectionQuery->whereIn('department_id', array_values(array_unique($departmentIds))));
                }

                if ($departmentIds !== []) {
                    $scopeQuery->orWhere(function ($sharedProjectQuery) use ($departmentIds): void {
                        $sharedProjectQuery->where('projects.is_system_default', true)
                            ->whereNull('projects.department_id')
                            ->whereHas('sections', fn ($sectionQuery) => $sectionQuery->whereIn('department_id', array_values(array_unique($departmentIds))));
                    });
                }
            }

            if (in_array(PermissionScope::Project->value, $scopes, true)) {
                $scopeQuery
                    ->orWhere('projects.manager_id', $user->id)
                    ->orWhereHas('users', fn ($userQuery) => $userQuery->where('users.id', $user->id))
                    ->orWhere(function ($sharedProjectQuery) use ($user): void {
                        $sharedProjectQuery->where('projects.is_system_default', true)
                            ->whereNull('projects.department_id')
                            ->whereHas('sections.department.members', fn ($memberQuery) => $memberQuery->where('users.id', $user->id));
                    });
            }
        });

        return $query->latest()->paginate(15)->withQueryString();
    }

    public function show(Project $project): Project
    {
        return $project->load([
            'customFieldValues.field',
            'manager',
            'creator',
            'department',
            'roles',
            'members.user',
            'members.projectRole',
            'documents.document.latestVersion',
            'tasks.assignee',
            'tasks.creator',
            'sections.department.manager',
            'sections.department.members' => fn ($query) => $query->where('users.status', 'active'),
            'sections.documents.document.latestVersion',
            'sections.tasks.assignee',
            'sections.tasks.creator',
        ]);
    }

    public function create(
        array $data,
        User $creator
    ): Project {
        return $this->createProjectAction->execute(
            data: $data,
            creator: $creator
        );
    }

    public function update(
        Project $project,
        array $data
    ): Project {
        return $this->updateProjectAction->execute(
            project: $project,
            data: $data
        );
    }

    public function delete(
        Project $project
    ): void {
        $this->deleteProjectAction->execute(
            project: $project
        );
    }
}