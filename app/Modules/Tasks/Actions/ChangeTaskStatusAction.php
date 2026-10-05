<?php

namespace App\Modules\Tasks\Actions;

use App\Modules\Tasks\Models\Task;
use App\Models\User;
use App\Core\Audit\Contracts\AuditServiceInterface;
use App\Modules\Tasks\Models\TaskStatusHistory;
use App\Modules\Tasks\Services\TaskDependencyService;
use InvalidArgumentException;

class ChangeTaskStatusAction
{
    public function __construct(
        private readonly TaskDependencyService $dependencyService,
        private readonly AuditServiceInterface $audit,
    ) {}

    private const ALLOWED_STATUSES = [
        'todo',
        'in_progress',
        'completed',
        'cancelled',
        'submitted',
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

        if ($status === 'submitted') {
            throw new InvalidArgumentException('ارسال تسک برای بررسی فقط از مسیر ارسال نتیجه کار امکان‌پذیر است.');
        }

        if (in_array($task->status, ['completed', 'cancelled'], true) && $status !== $task->status) {
            throw new InvalidArgumentException('تسک نهایی‌شده امکان تغییر وضعیت ندارد.');
        }

        if ($status === 'completed' && $task->review_required && $task->review_status !== 'approved') {
            throw new InvalidArgumentException('این تسک باید ابتدا توسط بررسی‌کننده تأیید شود.');
        }

        if ($status === 'in_progress' && ! $this->dependencyService->canStart($task)) {
            throw new InvalidArgumentException('تا تکمیل و تأیید تسک‌های پیش‌نیاز، امکان شروع این تسک وجود ندارد.');
        }

        $fromStatus = $task->status;

        $task->status = $status;

        if ($status === 'completed') {
            $task->completed_at ??= now();
        } else {
            $task->completed_at = null;
        }

        if ($status === 'submitted' && $task->review_required) {
            $task->review_status = 'pending';
            $task->review_requested_at = now();
        }

        $task->save();

        TaskStatusHistory::query()->create([
            'task_id' => $task->id,
            'from_status' => $fromStatus,
            'to_status' => $status,
            'changed_by' => $actor->id,
            'changed_at' => now(),
        ]);

        $task = $task->refresh()->load([
            'project',
            'assignee',
            'creator',
        ]);

        $this->audit->record('task.status_changed', $task, ['status' => $fromStatus], ['status' => $task->status]);

        return $task;

    }
}