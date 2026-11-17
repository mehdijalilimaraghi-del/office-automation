<?php

declare(strict_types=1);

namespace App\Modules\Tasks\Services;

use App\Core\Departments\Models\Department;
use App\Core\Permissions\Enums\PermissionScope;
use App\Core\ExternalParties\Models\{ExternalContact, ExternalDepartment, ExternalOrganization};
use App\Models\User;
use App\Modules\Projects\Models\Project;
use App\Modules\Tasks\Models\Task;
use App\Modules\Projects\Models\ProjectSection;

class TaskFormOptionsService
{
    public function __construct(
        private readonly TaskAccessService $taskAccessService,
    ) {
    }

    /**
     * @return array{projects: array<int, array{id:int,name:string}>, departments: array<int, array{id:int,name:string,parent_id:int|null,icon:string|null}>, users: array<int, array{id:int,name:string}>}
     */
    public function forFilters(User $user): array
    {
        $visibleTasks = $this->taskAccessService->applyScope(Task::query(), $user, 'tasks.view');
        $projectIds = (clone $visibleTasks)->whereNotNull('tasks.project_id')->distinct()->pluck('tasks.project_id');
        $departmentIds = (clone $visibleTasks)->whereNotNull('tasks.department_id')->distinct()->pluck('tasks.department_id');
        $sectionIds = (clone $visibleTasks)->whereNotNull('tasks.section_id')->select('tasks.section_id');
        $sectionDepartmentIds = ProjectSection::query()
            ->whereIn('id', $sectionIds)
            ->whereNotNull('department_id')
            ->distinct()
            ->pluck('department_id');

        return [
            'projects' => Project::query()->whereIn('id', $projectIds)->orderBy('name')->get(['id', 'name'])
                ->map(fn (Project $project): array => ['id' => (int) $project->id, 'name' => $project->name])->values()->all(),
            'departments' => Department::query()->whereIn('id', $departmentIds->merge($sectionDepartmentIds)->unique())
                ->orderBy('name')->get(['id', 'name', 'parent_id', 'icon'])
                ->map(fn (Department $department): array => ['id' => (int) $department->id, 'name' => $department->name, 'parent_id' => $department->parent_id !== null ? (int) $department->parent_id : null, 'icon' => $department->icon])->values()->all(),
            'users' => User::query()->operational()->where('status', 'active')
                ->whereIn('id', (clone $visibleTasks)->whereNotNull('tasks.assignee_id')->select('tasks.assignee_id'))
                ->orderBy('name')->get(['id', 'name'])
                ->map(fn (User $user): array => ['id' => (int) $user->id, 'name' => $user->name])->values()->all(),
        ];
    }

    /**
     * @return array{
     *     projects: array<int, array{id:int,name:string,member_ids:array<int,int>}>,
     *     departments: array<int, array{id:int,name:string,parent_id:int|null}>,
     *     users: array<int, array{id:int,name:string}>,
     *     external_organizations: array<int, array{id:int,name:string}>,
     *     external_departments: array<int, array{id:int,external_organization_id:int,parent_id:int|null,name:string}>,
     *     external_contacts: array<int, array{id:int,external_organization_id:int,external_department_id:int|null,name:string,position:string|null,mobile:string|null,phone:string|null,extension:string|null}>
     * }
     */
    public function forCreate(User $user): array
    {
        $departmentIds = $user->departments()
            ->pluck('departments.id')
            ->merge($user->managedDepartments()->pluck('departments.id'))
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        $taskCreateScopes = $user->permissionScopes('tasks.create');

        $projectsQuery = Project::query()
            ->where('projects.is_active', true)
            ->with(['users:id', 'department.members:id', 'sections:id,project_id,name,department_id,manager_can_create_tasks', 'sections.department.manager', 'sections.department.members:id']);

        if (in_array(PermissionScope::Organization->value, $taskCreateScopes, true)) {
            // Organization scope grants access to every active project.
        } else {
            $projectsQuery->where(function ($query) use ($user, $departmentIds, $taskCreateScopes): void {
                if (in_array(PermissionScope::Self->value, $taskCreateScopes, true)) {
                    $query
                        ->where('projects.created_by', $user->id)
                        ->orWhere('projects.manager_id', $user->id);
                }

                if (in_array(PermissionScope::Department->value, $taskCreateScopes, true) && $departmentIds !== []) {
                    $query->orWhereIn('projects.department_id', $departmentIds)
                        ->orWhere(function ($sharedProjectQuery) use ($departmentIds): void {
                            $sharedProjectQuery->where('projects.is_system_default', true)
                                ->whereNull('projects.department_id')
                                ->whereHas('sections', fn ($sectionQuery) => $sectionQuery->whereIn('department_id', $departmentIds));
                        });
                }

                if (in_array(PermissionScope::DepartmentTree->value, $taskCreateScopes, true)) {
                    $treeDepartmentIds = [];

                    $responsibleDepartments = $user->departments()
                        ->get()
                        ->merge($user->managedDepartments()->get())
                        ->unique('id');

                    foreach ($responsibleDepartments as $department) {
                        $treeDepartmentIds[] = (int) $department->id;
                        $treeDepartmentIds = array_merge($treeDepartmentIds, array_map('intval', $department->descendantIds()));
                    }

                    if ($treeDepartmentIds !== []) {
                        $treeDepartmentIds = array_values(array_unique($treeDepartmentIds));
                        $query->orWhereIn('projects.department_id', $treeDepartmentIds)
                            ->orWhere(function ($sharedProjectQuery) use ($treeDepartmentIds): void {
                                $sharedProjectQuery->where('projects.is_system_default', true)
                                    ->whereNull('projects.department_id')
                                    ->whereHas('sections', fn ($sectionQuery) => $sectionQuery->whereIn('department_id', $treeDepartmentIds));
                            });
                    }
                }

                if (in_array(PermissionScope::Project->value, $taskCreateScopes, true)) {
                    $query->orWhere('projects.manager_id', $user->id);
                }
            });
        }

        $managedDepartmentIds = $user->managedDepartments()
            ->where('is_active', true)
            ->pluck('departments.id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();

        if ($managedDepartmentIds !== []) {
            $projectsQuery->orWhere(function ($sharedProjectQuery) use ($managedDepartmentIds): void {
                $sharedProjectQuery->where('projects.is_active', true)
                    ->where('projects.is_system_default', true)
                    ->whereNull('projects.department_id')
                    ->whereHas('sections', fn ($sectionQuery) => $sectionQuery
                        ->whereIn('department_id', $managedDepartmentIds)
                        ->where('manager_can_create_tasks', true));
            });
        }

        $projects = $projectsQuery
            ->orderBy('projects.name')
            ->get()
            ->map(fn (Project $project): array => [
                'id' => (int) $project->id,
                'name' => $project->name,
                'sections' => $project->sections->map(fn ($section): array => [
                    'id' => (int) $section->id,
                    'name' => $section->name,
                    'department_id' => $section->department_id !== null ? (int) $section->department_id : null,
                    'manager_id' => $section->department?->manager_id !== null ? (int) $section->department->manager_id : null,
                    'manager_name' => $section->department?->manager?->name,
                    'manager_can_create_tasks' => (bool) $section->manager_can_create_tasks,
                    'member_ids' => $section->department?->members->pluck('id')->map(fn ($id): int => (int) $id)->values()->all() ?? [],
                ])->values()->all(),
                'member_ids' => $project->is_system_default && $project->department_id === null
                    ? User::query()->operational()->where('status', 'active')->pluck('id')->map(fn ($id): int => (int) $id)->values()->all()
                    : ($project->is_system_default && $project->department_id !== null
                    ? $project->department?->members
                        ->pluck('id')
                        ->map(fn ($id): int => (int) $id)
                        ->values()
                        ->all() ?? []
                    : $project->users
                        ->pluck('id')
                        ->map(fn ($id): int => (int) $id)
                        ->values()
                        ->all()),
            ])
            ->values()
            ->all();

        return [
            'projects' => $projects,
            'departments' => Department::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'parent_id', 'icon'])
                ->map(fn (Department $department): array => [
                    'id' => (int) $department->id,
                    'name' => $department->name,
                    'parent_id' => $department->parent_id !== null ? (int) $department->parent_id : null,
                    'icon' => $department->icon,
                ])
                ->values()
                ->all(),
            'users' => $this->activeUsers(),
            'external_organizations' => ExternalOrganization::query()->where('status', 'active')->orderBy('name')->get(['id','name'])->map(fn (ExternalOrganization $item): array => ['id'=>(int)$item->id,'name'=>$item->name])->values()->all(),
            'external_departments' => ExternalDepartment::query()->where('status', 'active')->orderBy('name')->get(['id','external_organization_id','parent_id','name'])->map(fn (ExternalDepartment $item): array => ['id'=>(int)$item->id,'external_organization_id'=>(int)$item->external_organization_id,'parent_id'=>$item->parent_id !== null ? (int)$item->parent_id : null,'name'=>$item->name])->values()->all(),
            'external_contacts' => ExternalContact::query()->where('status', 'active')->orderBy('name')->get(['id','external_organization_id','external_department_id','name','position','mobile','phone','extension','email'])->map(fn (ExternalContact $item): array => ['id'=>(int)$item->id,'external_organization_id'=>(int)$item->external_organization_id,'external_department_id'=>$item->external_department_id !== null ? (int)$item->external_department_id : null,'name'=>$item->name,'position'=>$item->position,'mobile'=>$item->mobile,'phone'=>$item->phone,'extension'=>$item->extension,'email'=>$item->email])->values()->all(),
        ];
    }

    /**
     * @return array<int, array{id:int,name:string}>
     */
    public function relatedUsers(): array
    {
        return $this->activeUsers();
    }

    /**
     * @return array<int, array{id:int,name:string}>
     */
    public function assigneesFor(Task $task): array
    {
        // Project membership is no longer required for task assignment.
        return $this->activeUsers();
    }

    /**
     * @return array<int, array{id:int,name:string}>
     */
    private function activeUsers(): array
    {
        return User::query()
            ->operational()
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $user): array => [
                'id' => (int) $user->id,
                'name' => $user->name,
            ])
            ->values()
            ->all();
    }
}
