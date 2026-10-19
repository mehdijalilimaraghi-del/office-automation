<?php

declare(strict_types=1);

namespace App\Modules\Tasks\Actions;

use App\Models\User;
use App\Core\Audit\Contracts\AuditServiceInterface;
use App\Modules\Tasks\Events\TaskSubmitted;
use App\Modules\Tasks\Models\Task;
use App\Modules\Tasks\Models\TaskStatusHistory;
use App\Modules\Tasks\Services\TaskDependencyService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SubmitTaskAction
{
    public function __construct(
        private readonly TaskDependencyService $dependencyService,
        private readonly AuditServiceInterface $audit,
    ) {
    }

    public function execute(Task $task, User $actor): Task
    {
        if ($task->isFinalized()) {
            throw new InvalidArgumentException('دستورکار نهایی‌شده امکان ارسال مجدد ندارد.');
        }

        if ($task->review_status === 'pending') {
            throw new InvalidArgumentException('این دستورکار در حال حاضر در انتظار بررسی مدیر است.');
        }

        if ((int) $task->assignee_id !== (int) $actor->id) {
            throw new InvalidArgumentException('فقط مسئول فعلی تسک می‌تواند نتیجه کار را ارسال کند.');
        }

        if (! in_array($task->status, ['in_progress', 'todo', 'observed'], true)) {
            throw new InvalidArgumentException('وضعیت فعلی تسک امکان ارسال برای بررسی را ندارد.');
        }

        if (! $this->dependencyService->canStart($task)) {
            throw new InvalidArgumentException('تا تکمیل و تأیید تسک‌های پیش‌نیاز، امکان ارسال این تسک وجود ندارد.');
        }

        return DB::transaction(function () use ($task, $actor): Task {
            $fromStatus = $task->status;

            $task->update([
                'status' => 'in_progress',
                'sub_status' => 'manager_review',
                'review_required' => true,
                'review_status' => 'pending',
                'review_requested_at' => now(),
                'reviewed_by' => null,
                'reviewed_at' => null,
                'review_note' => null,
            ]);

            TaskStatusHistory::query()->create([
                'task_id' => $task->id,
                'from_status' => $fromStatus,
                'to_status' => 'in_progress',
                'changed_by' => $actor->id,
                'changed_at' => now(),
            ]);

            $task = $task->refresh()->load(['project', 'department', 'assignee', 'creator']);

            TaskSubmitted::dispatch($task);
            $this->audit->record('task.submitted', $task, ['status' => $fromStatus], ['status' => $task->status, 'sub_status' => $task->sub_status, 'review_status' => $task->review_status]);

            return $task;
        });
    }
}
