<?php

declare(strict_types=1);

namespace App\Modules\Tasks\Actions;

use App\Models\User;
use App\Modules\Tasks\Models\Task;
use App\Modules\Tasks\Models\TaskStatusHistory;
use App\Modules\Tasks\Services\TaskDependencyService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SubmitTaskAction
{
    public function __construct(
        private readonly TaskDependencyService $dependencyService,
    ) {
    }

    public function execute(Task $task, User $actor): Task
    {
        if ((int) $task->assignee_id !== (int) $actor->id) {
            throw new InvalidArgumentException('فقط مسئول فعلی تسک می‌تواند نتیجه کار را ارسال کند.');
        }

        if (! $task->review_required) {
            throw new InvalidArgumentException('این تسک نیاز به بررسی و تأیید ندارد.');
        }

        if (! in_array($task->status, ['in_progress', 'todo'], true)) {
            throw new InvalidArgumentException('وضعیت فعلی تسک امکان ارسال برای بررسی را ندارد.');
        }

        if (! $this->dependencyService->canStart($task)) {
            throw new InvalidArgumentException('تا تکمیل و تأیید تسک‌های پیش‌نیاز، امکان ارسال این تسک وجود ندارد.');
        }

        return DB::transaction(function () use ($task, $actor): Task {
            $fromStatus = $task->status;

            $task->update([
                'status' => 'submitted',
                'review_status' => 'pending',
                'review_requested_at' => now(),
                'reviewed_by' => null,
                'reviewed_at' => null,
                'review_note' => null,
            ]);

            TaskStatusHistory::query()->create([
                'task_id' => $task->id,
                'from_status' => $fromStatus,
                'to_status' => 'submitted',
                'changed_by' => $actor->id,
                'changed_at' => now(),
            ]);

            return $task->refresh()->load(['project', 'department', 'assignee', 'creator']);
        });
    }
}
