<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Notifications;

use App\Modules\Tasks\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

final class TaskNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $notificationType,
        private readonly string $title,
        private readonly string $message,
        private readonly Task $task,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => $this->notificationType,
            'title' => $this->title,
            'message' => $this->message,
            'task_id' => (int) $this->task->id,
            'task_title' => $this->task->title,
            'url' => route('tasks.show', $this->task),
        ];
    }
}