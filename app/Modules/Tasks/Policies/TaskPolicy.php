<?php

declare(strict_types=1);

namespace App\Modules\Tasks\Policies;

use App\Models\User;
use App\Modules\Tasks\Models\Task;
use App\Modules\Tasks\Services\TaskAccessService;

class TaskPolicy
{
    public function __construct(
        private readonly TaskAccessService $taskAccessService,
    ) {
    }

    public function viewAny(User $user): bool
    {
        return $user->isActive() && $user->hasPermission('tasks.view');
    }

    public function view(User $user, Task $task): bool
    {
        return $this->taskAccessService->canAccess(
            user: $user,
            task: $task,
            permission: 'tasks.view',
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
        return $this->taskAccessService->canAccess(
            user: $user,
            task: $task,
            permission: 'tasks.update',
        );
    }

    public function assign(User $user, Task $task): bool
    {
        return $this->taskAccessService->canAccess(
            user: $user,
            task: $task,
            permission: 'tasks.assign',
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
        return $this->taskAccessService->canAccess(
            user: $user,
            task: $task,
            permission: 'tasks.comment',
        );
    }

    public function logWork(User $user, Task $task): bool
    {
        return $this->taskAccessService->canAccess(
            user: $user,
            task: $task,
            permission: 'tasks.log_work',
        );
    }

    public function createReminder(User $user, Task $task): bool
    {
        return $this->taskAccessService->canAccess(
            user: $user,
            task: $task,
            permission: 'tasks.create_reminder',
        );
    }

    public function changeStatus(User $user, Task $task): bool
    {
        return $this->taskAccessService->canAccess(
            user: $user,
            task: $task,
            permission: 'tasks.change_status',
        );
    }

    public function delete(User $user, Task $task): bool
    {
        return $this->taskAccessService->canAccess(
            user: $user,
            task: $task,
            permission: 'tasks.delete',
        );
    }
}
