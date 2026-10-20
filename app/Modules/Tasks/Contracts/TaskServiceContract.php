<?php

namespace App\Modules\Tasks\Contracts;

use App\Models\User;
use App\Modules\Tasks\Models\Task;
use App\Modules\Tasks\Models\TaskComment;
use App\Modules\Tasks\Models\TaskReminder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface TaskServiceContract
{
    public function list(User $user, array $filters = []): LengthAwarePaginator;
    public function create(array $data, User $creator): Task;
    public function update(Task $task, array $data): Task;
    public function assign(Task $task, User $assignee, ?User $actor = null, string $type = 'assignment', ?string $note = null): Task;
    public function changeStatus(Task $task, string $status, ?User $actor = null): Task;
    public function addComment(Task $task, User $author, string $body, ?int $parentCommentId = null): TaskComment;
    public function addReminder(Task $task, User $recipient, User $creator, string $title, $remindAt, ?string $description = null): TaskReminder;
    public function updateReminderStatus(TaskReminder $reminder, string $status, User $actor): TaskReminder;
    public function timeline(Task $task): array;
    public function delete(Task $task): void;
}
