<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Listeners;

use App\Models\User;
use App\Modules\Notifications\Services\NotificationService;
use App\Modules\Tasks\Events\TaskApproved;
use App\Modules\Tasks\Events\TaskAssigned;
use App\Modules\Tasks\Events\TaskReturned;
use App\Modules\Tasks\Events\TaskSubmitted;

final class TaskNotificationListener
{
    public function __construct(
        private readonly NotificationService $notifications,
    ) {
    }

    public function assigned(TaskAssigned $event): void
    {
        if ((int) $event->actor->id === (int) $event->assignee->id) {
            return;
        }

        $this->notifications->taskAssigned(
            $event->task,
            $event->assignee,
            $event->type === 'handoff' ? 'task_referred' : 'task_assigned',
        );
    }

    public function submitted(TaskSubmitted $event): void
    {
        $task = $event->task->loadMissing(['department', 'project']);
        $recipients = collect();

        if ($task->department?->manager_id) {
            $recipients->push((int) $task->department->manager_id);
        }

        if ($task->project?->manager_id) {
            $recipients->push((int) $task->project->manager_id);
        }

        User::query()
            ->whereIn('id', $recipients->unique()->values())
            ->where('status', 'active')
            ->get()
            ->each(fn (User $user) => $this->notifications->taskSubmitted($task, $user));
    }

    public function returned(TaskReturned $event): void
    {
        $task = $event->task->loadMissing('assignee');

        if ($task->assignee !== null) {
            $this->notifications->taskReturned($task, $task->assignee);
        }
    }

    public function approved(TaskApproved $event): void
    {
        $task = $event->task->loadMissing(['creator', 'assignee']);

        $recipients = collect([
            $task->creator,
            $task->assignee,
        ])->filter(fn (?User $user) => $user !== null)
            ->unique(fn (User $user) => $user->id);

        $recipients->each(fn (User $user) => $this->notifications->taskApproved($task, $user));
    }
}