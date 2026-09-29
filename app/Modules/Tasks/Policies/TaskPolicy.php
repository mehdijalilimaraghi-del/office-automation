<?php

declare(strict_types=1);

namespace App\Modules\Tasks\Policies;

use App\Models\User;
use App\Modules\Tasks\Models\Task;

class TaskPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isActive()
            && $user->hasPermission('tasks.view');
    }

    public function view(User $user, Task $task): bool
    {
        if (! $user->isActive() || ! $user->hasPermission('tasks.view')) {
            return false;
        }

        return $this->hasProjectAccess($user, $task);
    }

    public function create(User $user, int $projectId): bool
    {
        if (! $user->isActive() || ! $user->hasPermission('tasks.create')) {
            return false;
        }

        return $user->projects()
            ->where('projects.id', $projectId)
            ->exists();
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

    public function changeStatus(User $user, Task $task): bool
    {
        if (
            ! $user->isActive()
            || ! $user->hasPermission('tasks.change_status')
        ) {
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

    private function hasProjectAccess(User $user, Task $task): bool
    {
        return $this->isProjectManager($user, $task)
            || $task->project
                ->members()
                ->where('user_id', $user->id)
                ->exists();
    }

    private function isProjectManager(User $user, Task $task): bool
    {
        return (int) $task->project->manager_id === (int) $user->id;
    }
}