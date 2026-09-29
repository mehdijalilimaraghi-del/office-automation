<?php

namespace App\Modules\Tasks\Actions;

use App\Modules\Tasks\Models\Task;
use App\Models\User;
use App\Modules\Tasks\Models\TaskStatusHistory;
use InvalidArgumentException;

class ChangeTaskStatusAction
{
    private const ALLOWED_STATUSES = [
        'todo',
        'in_progress',
        'completed',
        'cancelled',
    ];

    public function execute(
        Task $task,
        string $status,
        User $actor
    ): Task {
        if (! in_array($status, self::ALLOWED_STATUSES, true)) {
            throw new InvalidArgumentException(
                'وضعیت تسک نامعتبر است.'
            );
        }

        $fromStatus = $task->status;

        $task->status = $status;

        if ($status === 'completed') {
            $task->completed_at ??= now();
        } else {
            $task->completed_at = null;
        }

        $task->save();

        TaskStatusHistory::query()->create([
            'task_id' => $task->id,
            'from_status' => $fromStatus,
            'to_status' => $status,
            'changed_by' => $actor->id,
            'changed_at' => now(),
        ]);

        return $task->refresh()->load([
            'project',
            'assignee',
            'creator',
        ]);
    }
}