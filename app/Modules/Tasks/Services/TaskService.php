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
use App\Modules\Tasks\Contracts\TaskServiceContract;
use App\Modules\Tasks\Models\Task;
use Illuminate\Support\Collection;

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
    ) {}

    public function list(): Collection
    {
        return Task::query()
            ->with(['project', 'department', 'assignee', 'creator'])
            ->latest()
            ->get();
    }

    public function create(array $data, User $creator): Task
    {
        return $this->createTaskAction->execute(data: $data, creator: $creator);
    }

    public function update(Task $task, array $data): Task
    {
        return $this->updateTaskAction->execute(task: $task, data: $data);
    }

    public function assign(Task $task, User $assignee, User $actor, string $type = 'assignment', ?string $note = null): Task
    {
        return $this->assignTaskAction->execute(
            task: $task,
            assignee: $assignee,
            actor: $actor,
            type: $type,
            note: $note,
        );
    }

    public function changeStatus(Task $task, string $status, User $actor): Task
    {
        return $this->changeTaskStatusAction->execute(
            task: $task,
            status: $status,
            actor: $actor,
        );
    }

    public function addComment(Task $task, User $author, string $body): \App\Modules\Tasks\Models\TaskComment
    {
        return $this->createTaskCommentAction->execute($task, $author, $body);
    }

    public function addWorkLog(
        Task $task,
        User $user,
        string $title,
        ?string $description = null,
        $startedAt = null,
        $completedAt = null,
    ): \App\Modules\Tasks\Models\TaskWorkLog {
        return $this->createTaskWorkLogAction->execute(
            task: $task,
            user: $user,
            title: $title,
            description: $description,
            startedAt: $startedAt,
            completedAt: $completedAt,
        );
    }

    public function addReminder(
        Task $task,
        User $recipient,
        User $creator,
        string $title,
        $remindAt,
        ?string $description = null,
    ): \App\Modules\Tasks\Models\TaskReminder {
        return $this->createTaskReminderAction->execute(
            task: $task,
            recipient: $recipient,
            creator: $creator,
            title: $title,
            remindAt: $remindAt,
            description: $description,
        );
    }

    public function delete(Task $task): void
    {
        $this->deleteTaskAction->execute($task);
    }
}
