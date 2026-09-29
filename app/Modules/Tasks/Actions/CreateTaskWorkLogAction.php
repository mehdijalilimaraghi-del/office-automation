<?php

declare(strict_types=1);

namespace App\Modules\Tasks\Actions;

use App\Models\User;
use App\Modules\Tasks\Models\Task;
use App\Modules\Tasks\Models\TaskWorkLog;

class CreateTaskWorkLogAction
{
    public function execute(
        Task $task,
        User $user,
        string $title,
        ?string $description = null,
        $startedAt = null,
        $completedAt = null,
    ): TaskWorkLog {
        return $task->workLogs()->create([
            'user_id' => $user->id,
            'title' => $title,
            'description' => $description,
            'started_at' => $startedAt,
            'completed_at' => $completedAt,
        ])->load('user');
    }
}
