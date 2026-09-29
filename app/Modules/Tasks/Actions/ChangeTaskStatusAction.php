<?php

namespace App\Modules\Tasks\Actions;

use App\Modules\Tasks\Models\Task;
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
        string $status
    ): Task {
        if (! in_array($status, self::ALLOWED_STATUSES, true)) {
            throw new InvalidArgumentException(
                'وضعیت تسک نامعتبر است.'
            );
        }

        $task->status = $status;

        if ($status === 'completed') {
            $task->completed_at ??= now();
        } else {
            $task->completed_at = null;
        }

        $task->save();

        return $task->refresh()->load([
            'project',
            'assignee',
            'creator',
        ]);
    }
}