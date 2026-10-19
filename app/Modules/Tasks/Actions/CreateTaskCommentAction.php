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
        if ($task->isFinalized()) {
            throw new \\InvalidArgumentException('برای دستورکار نهایی‌شده امکان ثبت نظر وجود ندارد.');
        }

        if ($task->review_status === 'pending') {
            throw new \\InvalidArgumentException('تا تعیین تکلیف مدیر، ثبت نظر امکان‌پذیر نیست.');
        }

        return $task->comments()->create([
            'user_id' => $author->id,
            'body' => $body,
        ])->load('user');
    }
}
