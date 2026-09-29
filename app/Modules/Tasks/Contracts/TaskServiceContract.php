<?php

namespace App\Modules\Tasks\Contracts;

use App\Models\User;
use App\Modules\Tasks\Models\Task;
use Illuminate\Support\Collection;

interface TaskServiceContract
{
    public function list(): Collection;
    public function create(array $data, User $creator): Task;
    public function update(Task $task, array $data): Task;
    public function assign(Task $task, User $assignee, User $actor, string $type = 'assignment', ?string $note = null): Task;
    public function changeStatus(Task $task, string $status): Task;
    public function delete(Task $task): void;
}
