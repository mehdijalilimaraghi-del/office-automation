<?php

namespace App\Modules\Tasks\Services;

use App\Models\User;
use App\Modules\Tasks\Actions\AssignTaskAction;
use App\Modules\Tasks\Actions\ChangeTaskStatusAction;
use App\Modules\Tasks\Actions\CreateTaskAction;
use App\Modules\Tasks\Actions\CreateTaskCommentAction;
use App\Modules\Tasks\Actions\CreateTaskReminderAction;
use App\Modules\Tasks\Actions\CreateTaskWorkLogAction;
use App\Modules\Tasks\Actions\DeleteTaskAction;
use App\Modules\Tasks\Actions\UpdateTaskAction;
use App\Modules\Tasks\Actions\UpdateTaskReminderStatusAction;
use App\Modules\Tasks\Contracts\TaskServiceContract;
use App\Modules\Tasks\Models\Task;
use App\Modules\Tasks\Models\TaskReminder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class TaskService implements TaskServiceContract
{
    public function __construct(
        private readonly CreateTaskAction $createTaskAction,
        private readonly UpdateTaskAction $updateTaskAction,
        private readonly AssignTaskAction $assignTaskAction,
        private readonly ChangeTaskStatusAction $changeTaskStatusAction,
        private readonly DeleteTaskAction $deleteTaskAction,
        private readonly CreateTaskCommentAction $createTaskCommentAction,
        private readonly CreateTaskWorkLogAction $createTaskWorkLogAction,
        private readonly CreateTaskReminderAction $createTaskReminderAction,
        private readonly UpdateTaskReminderStatusAction $updateTaskReminderStatusAction,
        private readonly TaskTimelineService $taskTimelineService,
        private readonly TaskAccessService $taskAccessService,
    ) {}

    public function list(User $user, array $filters = []): LengthAwarePaginator
    {
        $query = Task::query();

        $this->taskAccessService->applyScope(query: $query, user: $user, permission: 'tasks.view');

        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(function ($searchQuery) use ($search): void {
                $searchQuery
                    ->where('tasks.title', 'like', '%'.$search.'%')
                    ->orWhere('tasks.description', 'like', '%'.$search.'%')
                    ->orWhereHas('project', fn ($q) => $q->where('projects.name', 'like', '%'.$search.'%'))
                    ->orWhereHas('department', fn ($q) => $q->where('departments.name', 'like', '%'.$search.'%'))
                    ->orWhereHas('assignee', fn ($q) => $q->where('users.name', 'like', '%'.$search.'%'));
            });
        }

        if (! empty($filters['status'])) $query->where('tasks.status', $filters['status']);
        if (! empty($filters['priority'])) $query->where('tasks.priority', $filters['priority']);
        if (! empty($filters['project_id'])) $query->where('tasks.project_id', (int) $filters['project_id']);
        if (! empty($filters['department_id'])) $query->where('tasks.department_id', (int) $filters['department_id']);

        return $query->with(['project', 'department', 'assignee', 'creator'])->latest()->paginate(15)->withQueryString();
    }

    public function create(array $data, User $creator): Task
    {
        return $this->createTaskAction->execute(data: $data, creator: $creator);
    }

    public function update(Task $task, array $data): Task
    {
        return $this->updateTaskAction->execute(task: $task, data: $data);
    }

    public function assign(Task $task, User $assignee, ?User $actor = null, string $type = 'assignment', ?string $note = null): Task
    {
        $actor ??= $task->assignee ?? $assignee;

        return $this->assignTaskAction->execute(task: $task, assignee: $assignee, actor: $actor, type: $type, note: $note);
    }

    public function changeStatus(Task $task, string $status, ?User $actor = null): Task
    {
        $actor ??= $task->assignee;

        if ($actor === null) {
            throw new \InvalidArgumentException('Task must have an assignee or an actor must be provided.');
        }

        return $this->changeTaskStatusAction->execute(task: $task, status: $status, actor: $actor);
    }

    public function addComment(Task $task, User $author, string $body): \App\Modules\Tasks\Models\TaskComment
    {
        return $this->createTaskCommentAction->execute($task, $author, $body);
    }

    public function addWorkLog(Task $task, User $user, string $title, ?string $description = null, $startedAt = null, $completedAt = null): \App\Modules\Tasks\Models\TaskWorkLog
    {
        return $this->createTaskWorkLogAction->execute(task: $task, user: $user, title: $title, description: $description, startedAt: $startedAt, completedAt: $completedAt);
    }

    public function addReminder(Task $task, User $recipient, User $creator, string $title, $remindAt, ?string $description = null): TaskReminder
    {
        return $this->createTaskReminderAction->execute(task: $task, recipient: $recipient, creator: $creator, title: $title, remindAt: $remindAt, description: $description);
    }

    public function updateReminderStatus(TaskReminder $reminder, string $status, User $actor): TaskReminder
    {
        return $this->updateTaskReminderStatusAction->execute(reminder: $reminder, status: $status, actor: $actor);
    }

    public function timeline(Task $task): array
    {
        return $this->taskTimelineService->build($task);
    }

    public function delete(Task $task): void
    {
        $this->deleteTaskAction->execute($task);
    }
}
