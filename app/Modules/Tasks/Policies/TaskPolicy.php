<?php

declare(strict_types=1);

namespace App\Modules\Tasks\Policies;

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
        if (! $user->isActive() || ! $user->hasPermission('tasks.view')) {
            return false;
        }

        return $this->hasProjectAccess($user, $task)
            || $task->assignee_id === $user->id
            || $task->created_by === $user->id;
    }

    public function create(User $user, ?int $projectId = null): bool
    {
        if (! $user->isActive() || ! $user->hasPermission('tasks.create')) {
            return false;
        }

        if ($projectId === null) {
            return true;
        }

        return $user->projects()->where('projects.id', $projectId)->exists();
    }

    public function update(User $user, Task $task): bool
    {
        if (! $user->isActive() || ! $user->hasPermission('tasks.update')) {
            return false;
        }

        return $this->isProjectManager($user, $task)
            || $task->created_by === $user->id;
    }

    public function assign(User $user, Task $task): bool
    {
        return $user->isActive()
            && $user->hasPermission('tasks.assign')
            && $this->isProjectManager($user, $task);
    }

    public function refer(User $user, Task $task): bool
    {
        return $user->isActive()
            && $user->hasPermission('tasks.refer')
            && (int) $task->assignee_id === (int) $user->id;
    }

    public function comment(User $user, Task $task): bool
    {
        return $this->canAccessTask($user, $task, 'tasks.comment');
    }

    public function logWork(User $user, Task $task): bool
    {
        return $this->canAccessTask($user, $task, 'tasks.log_work');
    }

    public function createReminder(User $user, Task $task): bool
    {
        return $this->canAccessTask($user, $task, 'tasks.create_reminder');
    }

    public function changeStatus(User $user, Task $task): bool
    {
        if (! $user->isActive() || ! $user->hasPermission('tasks.change_status')) {
            return false;
        }

        return $this->isProjectManager($user, $task)
            || $task->assignee_id === $user->id;
    }

    public function delete(User $user, Task $task): bool
    {
        if (! $user->isActive() || ! $user->hasPermission('tasks.delete')) {
            return false;
        }

        return $this->isProjectManager($user, $task)
            || $task->created_by === $user->id;
    }

    private function canAccessTask(User $user, Task $task, string $permission): bool
    {
        if (! $user->isActive() || ! $user->hasPermission($permission)) {
            return false;
        }

        return $this->hasProjectAccess($user, $task)
            || (int) $task->assignee_id === (int) $user->id
            || (int) $task->created_by === (int) $user->id;
    }

    private function hasProjectAccess(User $user, Task $task): bool
    {
        return $task->project !== null
            && (
                $this->isProjectManager($user, $task)
                || $task->project->members()->where('user_id', $user->id)->exists()
            );
    }

    private function isProjectManager(User $user, Task $task): bool
    {
        return $task->project !== null
            && (int) $task->project->manager_id === (int) $user->id;
    }
}
