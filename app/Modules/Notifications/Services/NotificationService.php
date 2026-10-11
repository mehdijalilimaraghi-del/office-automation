<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Services;

use App\Models\User;
use App\Modules\Notifications\Notifications\TaskNotification;
use App\Modules\Tasks\Models\Task;

final class NotificationService
{
    public function taskAssigned(Task $task, User $recipient, string $type = 'task_assigned'): void
    {
        $recipient->notify(new TaskNotification(
            notificationType: $type,
            title: $type === 'task_referred' ? 'دستورکار به شما ارجاع شد' : 'دستورکار جدید به شما تخصیص یافت',
            message: $task->title,
            task: $task,
        ));
    }

    public function taskSubmitted(Task $task, User $recipient): void
    {
        $recipient->notify(new TaskNotification(
            notificationType: 'task_submitted',
            title: 'دستورکار در انتظار تأیید شماست',
            message: $task->title,
            task: $task,
        ));
    }

    public function taskReturned(Task $task, User $recipient): void
    {
        $recipient->notify(new TaskNotification(
            notificationType: 'task_returned',
            title: 'دستورکار برای اصلاح بازگردانده شد',
            message: $task->review_note ?: $task->title,
            task: $task,
        ));
    }

    public function taskApproved(Task $task, User $recipient): void
    {
        $recipient->notify(new TaskNotification(
            notificationType: 'task_approved',
            title: 'دستورکار تأیید و تکمیل شد',
            message: $task->title,
            task: $task,
        ));
    }
}