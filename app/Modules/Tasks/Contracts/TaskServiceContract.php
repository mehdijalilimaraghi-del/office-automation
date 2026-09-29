<?php

namespace App\Modules\Tasks\Contracts;

use App\Models\User;
use App\Modules\Tasks\Models\Task;

interface TaskServiceContract
{
    public function create(
        array $data,
        User $creator
    ): Task;

    public function update(
        Task $task,
        array $data
    ): Task;

    public function assign(
        Task $task,
        User $assignee
    ): Task;

    public function changeStatus(
        Task $task,
        string $status
    ): Task;
}