<?php

declare(strict_types=1);

namespace App\Modules\Tasks\Services;

use App\Core\Departments\Models\Department;
use App\Models\User;
use App\Modules\Projects\Models\Project;
use App\Modules\Tasks\Models\Task;

class TaskFormOptionsService
{
    /**
     * @return array{
     *     projects: array<int, array{id:int,name:string,member_ids:array<int,int>}>,
     *     departments: array<int, array{id:int,name:string}>,
     *     users: array<int, array{id:int,name:string}>
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
