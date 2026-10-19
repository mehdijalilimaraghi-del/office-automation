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

        $scopes = $user->permissionScopes('tasks.create');

        if ($projectId === null) {
            // Unlinked work orders are organization-level records. A project or
            // department manager must choose a resource within their scope.
            return in_array(\App\Core\Permissions\Enums\PermissionScope::Organization->value, $scopes, true);
        }

        if (in_array(\App\Core\Permissions\Enums\PermissionScope::Organization->value, $scopes, true)) {
            return true;
        }

        $project = Project::query()
            ->with('department')
            ->whereKey($projectId)
            ->where('is_active', true)
            ->first();

        if ($project === null) {
            return false;
        }

        $responsibleDepartmentIds = $user->departments()
            ->pluck('departments.id')
            ->merge($user->managedDepartments()->pluck('departments.id'))
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        if (
            in_array(\App\Core\Permissions\Enums\PermissionScope::Department->value, $scopes, true)
            && $project->department_id !== null
            && $responsibleDepartmentIds->contains((int) $project->department_id)
        ) {
            return true;
        }

        if (in_array(\App\Core\Permissions\Enums\PermissionScope::DepartmentTree->value, $scopes, true)) {
            $departments = $user->departments()
                ->get()
                ->merge($user->managedDepartments()->get())
                ->unique('id');

            foreach ($departments as $department) {
                if (in_array(
                    (int) $project->department_id,
                    array_map('intval', array_merge([$department->id], $department->descendantIds())),
                    true
                )) {
                    return true;
                }
            }
        }

        if (
            in_array(\App\Core\Permissions\Enums\PermissionScope::Project->value, $scopes, true)
            && (int) $project->manager_id === (int) $user->id
        ) {
            return true;
        }

        return in_array(\App\Core\Permissions\Enums\PermissionScope::Self->value, $scopes, true)
            && (
                (int) $project->created_by === (int) $user->id
                || (int) $project->manager_id === (int) $user->id
            );
    }

    public function update(User $user, Task $task): bool
    {
        return ! $task->isFinalized() && $task->review_status !== 'pending' && $this->taskAccessService->canAccess($user, $task, 'tasks.update');
    }

    public function assign(User $user, Task $task): bool
    {
        return ! $task->isFinalized() && $task->review_status !== 'pending' && $this->taskAccessService->canAccess($user, $task, 'tasks.assign');
    }

    public function refer(User $user, Task $task): bool
    {
        return ! $task->isFinalized()
            && $task->review_status !== 'pending'
            && (int) $task->assignee_id === (int) $user->id
            && $this->taskAccessService->canAccess($user, $task, 'tasks.refer');
    }

    public function comment(User $user, Task $task): bool
    {
        return ! $task->isFinalized() && $task->review_status !== 'pending' && $this->taskAccessService->canAccess($user, $task, 'tasks.view');
    }

    public function logWork(User $user, Task $task): bool
    {
        return ! $task->isFinalized() && $task->review_status !== 'pending' && $this->taskAccessService->canAccess($user, $task, 'tasks.log_work');
    }

    public function createReminder(User $user, Task $task): bool
    {
        return ! $task->isFinalized() && $task->review_status !== 'pending' && $this->taskAccessService->canAccess($user, $task, 'tasks.create_reminder');
    }

    public function completeReminder(User $user, Task $task, TaskReminder $reminder): bool
    {
        return ! $task->isFinalized()
            && $task->review_status !== 'pending'
            && (int) $reminder->task_id === (int) $task->id
            && (int) $reminder->user_id === (int) $user->id
            && $this->taskAccessService->canAccess($user, $task, 'tasks.view');
    }

    public function cancelReminder(User $user, Task $task, TaskReminder $reminder): bool
    {
        return ! $task->isFinalized()
            && $task->review_status !== 'pending'
            && (int) $reminder->task_id === (int) $task->id
            && in_array((int) $user->id, [(int) $reminder->user_id, (int) $reminder->created_by], true)
            && $this->taskAccessService->canAccess($user, $task, 'tasks.view');
    }

    public function submit(User $user, Task $task): bool
    {
        return ! $task->isFinalized()
            && $user->isActive()
            && (int) $task->assignee_id === (int) $user->id
            && $task->review_status !== 'pending'
            && in_array($task->status, ['todo', 'observed', 'in_progress'], true);
    }

    public function review(User $user, Task $task): bool
    {
        if (! $this->taskAccessService->canAccess($user, $task, 'tasks.review')) {
            return false;
        }

        return ! $task->isFinalized()
            && $task->review_status === 'pending'
            && ($task->department?->manager_id === $user->id || $task->project?->manager_id === $user->id);
    }

    public function changeStatus(User $user, Task $task): bool
    {
        if ($task->isFinalized() || $task->review_status === 'pending' || ! $this->taskAccessService->canAccess($user, $task, 'tasks.change_status')) {
            return false;
        }

        return (int) $task->department?->manager_id === (int) $user->id
            || (int) $task->project?->manager_id === (int) $user->id;
    }

    public function delete(User $user, Task $task): bool
    {
        return ! $task->isFinalized() && $task->review_status !== 'pending' && $this->taskAccessService->canAccess($user, $task, 'tasks.delete');
    }
}
