<?php

declare(strict_types=1);

namespace App\Modules\Tasks\Actions;

use App\Models\User;
use App\Modules\Tasks\Models\Task;
use App\Modules\Tasks\Models\TaskReminder;

class CreateTaskReminderAction
{
    public function execute(
        Task $task,
        User $recipient,
        User $creator,
        string $title,
        $remindAt,
        ?string $description = null,
    ): TaskReminder {
        if ($task->isFinalized()) {
            throw new \\InvalidArgumentException('برای دستورکار نهایی‌شده امکان ثبت یادآوری وجود ندارد.');
        }

        return $task->reminders()->create([
            'user_id' => $recipient->id,
            'created_by' => $creator->id,
            'title' => $title,
            'description' => $description,
            'remind_at' => $remindAt,
            'status' => 'pending',
        ])->load(['user', 'creator']);
    }
}
