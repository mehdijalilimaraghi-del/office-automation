<?php

declare(strict_types=1);

namespace App\Modules\Tasks\Actions;

use App\Models\User;
use App\Core\Audit\Contracts\AuditServiceInterface;
use App\Modules\Tasks\Events\TaskReturned;
use App\Modules\Tasks\Events\TaskApproved;
use App\Modules\Tasks\Models\Task;
use App\Modules\Tasks\Models\TaskStatusHistory;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ReviewTaskAction
{
    public function __construct(private readonly AuditServiceInterface $audit)
    {
    }

    public function approve(Task $task, User $reviewer): Task
    {
        $this->authorizeReviewer($task, $reviewer);

        if ($task->review_status !== 'pending' || $task->sub_status !== 'manager_review') {
            throw new InvalidArgumentException('این تسک در انتظار بررسی نیست.');
        }

        return DB::transaction(function () use ($task, $reviewer): Task {
            $task->update([
                'status' => 'completed',
                'sub_status' => null,
                'review_status' => 'approved',
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'completed_at' => now(),
                'review_note' => null,
            ]);

            TaskStatusHistory::query()->create([
                'task_id' => $task->id,
                'from_status' => 'in_progress',
                'to_status' => 'completed',
                'changed_by' => $reviewer->id,
                'changed_at' => now(),
            ]);

            $task = $task->refresh()->load(['project', 'department', 'assignee', 'creator', 'reviewedBy']);

            TaskApproved::dispatch($task);
            $this->audit->record('task.approved', $task, ['status' => 'in_progress', 'sub_status' => 'manager_review', 'review_status' => 'pending'], ['status' => $task->status, 'review_status' => $task->review_status, 'review_note' => $task->review_note]);

            return $task;
        });
    }

    public function returnTask(Task $task, User $reviewer, string $note): Task
    {
        $this->authorizeReviewer($task, $reviewer);

        if ($task->review_status !== 'pending' || $task->sub_status !== 'manager_review') {
            throw new InvalidArgumentException('این تسک در انتظار بررسی نیست.');
        }

        return DB::transaction(function () use ($task, $reviewer, $note): Task {
            $task->update([
                'status' => 'in_progress',
                'sub_status' => 'returned_for_completion',
                'review_status' => 'rejected',
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'review_note' => $note,
                'completed_at' => null,
            ]);

            TaskStatusHistory::query()->create([
                'task_id' => $task->id,
                'from_status' => 'in_progress',
                'to_status' => 'in_progress',
                'changed_by' => $reviewer->id,
                'changed_at' => now(),
            ]);

            $task = $task->refresh()->load(['project', 'department', 'assignee', 'creator', 'reviewedBy']);

            TaskReturned::dispatch($task);
            $this->audit->record('task.returned', $task, ['status' => 'in_progress', 'sub_status' => 'manager_review', 'review_status' => 'pending'], ['status' => $task->status, 'review_status' => $task->review_status, 'review_note' => $task->review_note]);

            return $task;
        });
    }

    private function authorizeReviewer(Task $task, User $reviewer): void
    {
        if (! $reviewer->isActive() || ! $reviewer->hasPermission('tasks.review')) {
            throw new InvalidArgumentException('کاربر مجوز بررسی تسک را ندارد.');
        }

        $isDepartmentManager = $task->department()
            ->where('manager_id', $reviewer->getKey())
            ->exists();
        $isProjectManager = $task->project()
            ->where('manager_id', $reviewer->getKey())
            ->exists();

        if (! $isDepartmentManager && ! $isProjectManager) {
            throw new InvalidArgumentException('فقط مدیر واحد یا مدیر پروژه می‌تواند این تسک را بررسی کند.');
        }
    }
}
