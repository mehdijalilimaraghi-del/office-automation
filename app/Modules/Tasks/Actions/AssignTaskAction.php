<?php

declare(strict_types=1);

namespace App\Modules\Tasks\Actions;

use App\Models\User;
use App\Modules\Tasks\Models\Task;
use App\Modules\Tasks\Models\TaskAssignment;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AssignTaskAction
{
    public function execute(
        Task $task,
        User $assignee,
        User $actor,
        string $type = 'assignment',
        ?string $note = null
    ): Task {
        if (! $assignee->isActive()) {
            throw new InvalidArgumentException('مسئول تسک باید کاربر فعال باشد.');
        }

        if ($task->project !== null) {
            $isProjectMember = $task->project
                ->members()
                ->where('user_id', $assignee->id)
                ->exists();

            if (! $isProjectMember) {
                throw new InvalidArgumentException('مسئول تسک باید عضو همان پروژه باشد.');
            }
        }

        return DB::transaction(function () use ($task, $assignee, $actor, $type, $note): Task {
            $current = $task->assignments()
                ->whereNull('ended_at')
                ->latest('started_at')
                ->first();

            if ($current !== null) {
                $current->update(['ended_at' => now()]);
            }

            $task->update(['assignee_id' => $assignee->id]);

            TaskAssignment::query()->create([
                'task_id' => $task->id,
                'from_user_id' => $current?->to_user_id ?? $task->getOriginal('assignee_id'),
                'to_user_id' => $assignee->id,
                'assigned_by' => $actor->id,
                'type' => $type,
                'note' => $note,
                'started_at' => now(),
            ]);

            return $task->refresh()->load([
                'project',
                'department',
                'assignee',
                'creator',
                'assignments.fromUser',
                'assignments.toUser',
                'assignments.assignedBy',
            ]);
        });
    }
}
