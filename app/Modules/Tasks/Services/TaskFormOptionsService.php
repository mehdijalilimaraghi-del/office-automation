<?php

declare(strict_types=1);

namespace App\Modules\Tasks\Services;

use App\Core\Departments\Models\Department;
use App\Core\ExternalParties\Models\{ExternalContact, ExternalDepartment, ExternalOrganization};
use App\Models\User;
use App\Modules\Projects\Models\Project;
use App\Modules\Tasks\Models\Task;

class TaskFormOptionsService
{
    /**
     * @return array{
     *     projects: array<int, array{id:int,name:string,member_ids:array<int,int>}>,
     *     departments: array<int, array{id:int,name:string}>,
     *     users: array<int, array{id:int,name:string}>,
     *     external_organizations: array<int, array{id:int,name:string}>,
     *     external_departments: array<int, array{id:int,external_organization_id:int,name:string}>,
     *     external_contacts: array<int, array{id:int,external_organization_id:int,external_department_id:int|null,name:string,position:string|null,mobile:string|null,phone:string|null,extension:string|null}>
     * }
     */
    public function forCreate(User $user): array
    {
        $projects = $user->projects()
            ->where('projects.is_active', true)
            ->with('users:id')
            ->orderBy('projects.name')
            ->get()
            ->map(fn (Project $project): array => [
                'id' => (int) $project->id,
                'name' => $project->name,
                'member_ids' => $project->users
                    ->pluck('id')
                    ->map(fn ($id): int => (int) $id)
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();

        return [
            'projects' => $projects,
            'departments' => Department::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Department $department): array => [
                    'id' => (int) $department->id,
                    'name' => $department->name,
                ])
                ->values()
                ->all(),
            'users' => $this->activeUsers(),
            'external_organizations' => ExternalOrganization::query()->where('status', 'active')->orderBy('name')->get(['id','name'])->map(fn (ExternalOrganization $item): array => ['id'=>(int)$item->id,'name'=>$item->name])->values()->all(),
            'external_departments' => ExternalDepartment::query()->where('status', 'active')->orderBy('name')->get(['id','external_organization_id','name'])->map(fn (ExternalDepartment $item): array => ['id'=>(int)$item->id,'external_organization_id'=>(int)$item->external_organization_id,'name'=>$item->name])->values()->all(),
            'external_contacts' => ExternalContact::query()->where('status', 'active')->orderBy('name')->get(['id','external_organization_id','external_department_id','name','position','mobile','phone','extension'])->map(fn (ExternalContact $item): array => ['id'=>(int)$item->id,'external_organization_id'=>(int)$item->external_organization_id,'external_department_id'=>$item->external_department_id !== null ? (int)$item->external_department_id : null,'name'=>$item->name,'position'=>$item->position,'mobile'=>$item->mobile,'phone'=>$item->phone,'extension'=>$item->extension])->values()->all(),
        ];
    }

    /**
     * @return array<int, array{id:int,name:string}>
     */
    public function assigneesFor(Task $task): array
    {
        if ($task->project_id === null) {
            return $this->activeUsers();
        }

        return $task->project
            ? $task->project->users()
                ->where('users.status', 'active')
                ->orderBy('users.name')
                ->get(['users.id', 'users.name'])
                ->map(fn (User $user): array => [
                    'id' => (int) $user->id,
                    'name' => $user->name,
                ])
                ->values()
                ->all()
            : [];
    }

    /**
     * @return array<int, array{id:int,name:string}>
     */
    private function activeUsers(): array
    {
        return User::query()
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
