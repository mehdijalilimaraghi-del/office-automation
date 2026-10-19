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
        'cancelled',
        'cannot_continue',
    ];

    public function execute(
        Task $task,
        string $status,
        User $actor
    ): Task {
        if ($task->isFinalized()) {
            throw new InvalidArgumentException('دستورکار نهایی‌شده امکان هیچ تغییری ندارد.');
        }

        if (! in_array($status, self::ALLOWED_STATUSES, true)) {
            throw new InvalidArgumentException('فقط مدیر می‌تواند دستورکار را لغو کند یا امکان ادامه نداشتن آن را ثبت کند.');
        }

        if ($task->review_status === 'pending') {
            throw new InvalidArgumentException('تا تعیین تکلیف مدیر، وضعیت دستورکار قابل تغییر نیست.');
        }

        if (in_array($status, ['cancelled', 'cannot_continue'], true)) {
            $isDepartmentManager = (int) $task->department?->manager_id === (int) $actor->id;
            $isProjectManager = (int) $task->project?->manager_id === (int) $actor->id;
            if (! $isDepartmentManager && ! $isProjectManager) {
                throw new InvalidArgumentException('فقط مدیر واحد یا مدیر پروژه می‌تواند وضعیت نهایی را تعیین کند.');
            }
        }

        $fromStatus = $task->status;

        $task->status = $status;
        if (in_array($status, ['cancelled', 'cannot_continue'], true)) {
            $task->sub_status = null;
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