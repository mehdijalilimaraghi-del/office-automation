<?php

declare(strict_types=1);

namespace App\Modules\Tasks\Policies;

use App\Models\User;
use App\Modules\Projects\Models\Project;
use App\Modules\Tasks\Models\Task;
use App\Modules\Tasks\Models\TaskReminder;
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
        return $this->taskAccessService->canAccess($user, $task, 'tasks.view');
    }

    public function create(User $user, ?int $projectId = null): bool
    {
        if (! $user->isActive() || ! $user->hasPermission('tasks.create')) {
            return false;
        }

        if ($projectId === null) {
            return true;
        }

        return $user->projects()->where('projects.id', $projectId)->exists()
            || Project::query()
                ->whereKey($projectId)
                ->where('is_active', true)
                ->where('is_system_default', true)
                ->whereIn(
                    'department_id',
                    $user->departments()->pluck('departments.id')
                )
                ->exists();
    }

    public function update(User $user, Task $task): bool
    {
        return $this->taskAccessService->canAccess($user, $task, 'tasks.update');
    }

    public function assign(User $user, Task $task): bool
    {
        return $this->taskAccessService->canAccess($user, $task, 'tasks.assign');
    }

    public function refer(User $user, Task $task): bool
    {
        return (int) $task->assignee_id === (int) $user->id
            && $this->taskAccessService->canAccess($user, $task, 'tasks.refer');
    }

    public function comment(User $user, Task $task): bool
    {
        return $this->taskAccessService->canAccess($user, $task, 'tasks.comment');
    }

    public function logWork(User $user, Task $task): bool
    {
        return $this->taskAccessService->canAccess($user, $task, 'tasks.log_work');
    }

    public function createReminder(User $user, Task $task): bool
    {
        return $this->taskAccessService->canAccess($user, $task, 'tasks.create_reminder');
    }

    public function completeReminder(User $user, Task $task, TaskReminder $reminder): bool
    {
        return (int) $reminder->task_id === (int) $task->id
            && (int) $reminder->user_id === (int) $user->id
            && $this->taskAccessService->canAccess($user, $task, 'tasks.view');
    }

    public function cancelReminder(User $user, Task $task, TaskReminder $reminder): bool
    {
        return (int) $reminder->task_id === (int) $task->id
            && in_array((int) $user->id, [(int) $reminder->user_id, (int) $reminder->created_by], true)
            && $this->taskAccessService->canAccess($user, $task, 'tasks.view');
    }

    public function submit(User $user, Task $task): bool
    {
        return (int) $task->assignee_id === (int) $user->id
            && $task->review_required
            && $this->taskAccessService->canAccess($user, $task, 'tasks.submit');
    }

    public function review(User $user, Task $task): bool
    {
        if (! $this->taskAccessService->canAccess($user, $task, 'tasks.review')) {
            return false;
        }

        return $task->review_status === 'pending'
            && ($task->department?->manager_id === $user->id || $task->project?->manager_id === $user->id);
    }

    public function changeStatus(User $user, Task $task): bool
    {
        return $this->taskAccessService->canAccess($user, $task, 'tasks.change_status');
    }

    public function delete(User $user, Task $task): bool
    {
        return $this->taskAccessService->canAccess($user, $task, 'tasks.delete');
    }
}
