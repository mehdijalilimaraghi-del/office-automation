<?php

declare(strict_types=1);

namespace App\Modules\Tasks\Policies;

use App\Core\Permissions\Enums\PermissionScope;
use App\Core\Departments\Models\Department;
use App\Models\User;
use App\Modules\Tasks\Models\Task;

class TaskPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isActive() && $user->hasPermission('tasks.view');
    }

    public function view(User $user, Task $task): bool
    {
        return $this->canAccessTask(
            $user,
            $task,
            'tasks.view'
        );
    }

    public function create(User $user, ?int $projectId = null): bool
    {
        if (! $user->isActive() || ! $user->hasPermission('tasks.create')) {
            return false;
        }

        if ($projectId === null) {
            return true;
        }

        return $user->projects()
            ->where('projects.id', $projectId)
            ->exists();
    }

    public function update(User $user, Task $task): bool
    {
        return $this->canAccessTask(
            $user,
            $task,
            'tasks.update'
        );
    }

    public function assign(User $user, Task $task): bool
    {
        return $this->canAccessTask(
            $user,
            $task,
            'tasks.assign'
        );
    }

    public function refer(User $user, Task $task): bool
    {
        return $user->isActive()
            && $user->hasPermission('tasks.refer')
            && (int) $task->assignee_id === (int) $user->id;
    }

    public function comment(User $user, Task $task): bool
    {
        return $this->canAccessTask(
            $user,
            $task,
            'tasks.comment'
        );
    }

    public function logWork(User $user, Task $task): bool
    {
        return $this->canAccessTask(
            $user,
            $task,
            'tasks.log_work'
        );
    }

    public function createReminder(User $user, Task $task): bool
    {
        return $this->canAccessTask(
            $user,
            $task,
            'tasks.create_reminder'
        );
    }

    public function changeStatus(User $user, Task $task): bool
    {
        return $this->canAccessTask(
            $user,
            $task,
            'tasks.change_status'
        );
    }

    public function delete(User $user, Task $task): bool
    {
        return $this->canAccessTask(
            $user,
            $task,
            'tasks.delete'
        );
    }

    private function canAccessTask(
        User $user,
        Task $task,
        string $permission
    ): bool {
        if (! $user->isActive()) {
            return false;
        }

        $scopes = $user->permissionScopes($permission);

        if ($scopes === []) {
            return false;
        }

        foreach ($scopes as $scope) {
            if ($this->scopeAllowsTask($user, $task, $scope)) {
                return true;
            }
        }

        return false;
    }

    private function scopeAllowsTask(
        User $user,
        Task $task,
        string $scope
    ): bool {
        return match ($scope) {
            PermissionScope::Self->value =>
                $this->isSelfTask($user, $task),

            PermissionScope::Department->value =>
                $this->isSelfTask($user, $task)
                || $this->belongsToUserDepartment($user, $task),

            PermissionScope::DepartmentTree->value =>
                $this->isSelfTask($user, $task)
                || $this->belongsToUserDepartmentTree($user, $task),

            PermissionScope::Project->value =>
                $this->isSelfTask($user, $task)
                || $this->hasProjectAccess($user, $task),

            PermissionScope::Organization->value => true,

            default => false,
        };
    }

    private function isSelfTask(User $user, Task $task): bool
    {
        return (int) $task->assignee_id === (int) $user->id
            || (int) $task->created_by === (int) $user->id;
    }

    private function belongsToUserDepartment(
        User $user,
        Task $task
    ): bool {
        if ($task->department_id === null) {
            return false;
        }

        return $user->departments()
            ->where('departments.id', $task->department_id)
            ->exists();
    }

    private function belongsToUserDepartmentTree(
        User $user,
        Task $task
    ): bool {
        if ($task->department_id === null) {
            return false;
        }

        $departmentIds = $user->departments()
            ->pluck('departments.id')
            ->map(fn ($id) => (int) $id)
            ->all();

        foreach ($departmentIds as $departmentId) {
            $department = Department::query()->find($departmentId);

            if ($department === null) {
                continue;
            }

            if (
                $task->department_id === $department->id
                || in_array(
                    $task->department_id,
                    $department->descendantIds(),
                    true
                )
            ) {
                return true;
            }
        }

        return false;
    }

    private function hasProjectAccess(
        User $user,
        Task $task
    ): bool {
        if ($task->project === null) {
            return false;
        }

        return $this->isProjectManager($user, $task)
            || $task->project
                ->members()
                ->where('user_id', $user->id)
                ->exists();
    }

    private function isProjectManager(
        User $user,
        Task $task
    ): bool {
        return $task->project !== null
            && (int) $task->project->manager_id === (int) $user->id;
    }
}
