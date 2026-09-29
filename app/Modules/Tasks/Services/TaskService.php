<?php

namespace App\Modules\Tasks\Services;

use App\Models\User;
use App\Modules\Tasks\Actions\AssignTaskAction;
use App\Modules\Tasks\Actions\ChangeTaskStatusAction;
use App\Modules\Tasks\Actions\CreateTaskAction;
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
    ) {
    }

    public function list(): Collection
    {
        return Task::query()
            ->with(['project', 'assignee', 'creator'])
            ->latest()
            ->get();
    }

    public function create(
        array $data,
        User $creator
    ): Task {
        return $this->createTaskAction->execute(
            data: $data,
            creator: $creator
        );
    }

    public function update(
        Task $task,
        array $data
    ): Task {
        return $this->updateTaskAction->execute(
            task: $task,
            data: $data
        );
    }

    public function assign(
        Task $task,
        User $assignee
    ): Task {
        return $this->assignTaskAction->execute(
            task: $task,
            assignee: $assignee
        );
    }

    public function changeStatus(
        Task $task,
        string $status
    ): Task {
        return $this->changeTaskStatusAction->execute(
            task: $task,
            status: $status
        );
    }

    public function delete(Task $task): void
    {
        $this->deleteTaskAction->execute($task);
    }
}