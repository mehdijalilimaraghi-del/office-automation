<?php

declare(strict_types=1);

namespace App\Modules\Tasks\Actions;

use App\Models\User;
use App\Modules\Tasks\Models\Task;
use App\Modules\Tasks\Models\TaskComment;

class CreateTaskCommentAction
{
    public function execute(Task $task, User $author, string $body): TaskComment
    {
        return $task->comments()->create([
            'user_id' => $author->id,
            'body' => $body,
        ])->load('user');
    }
}
