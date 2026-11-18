<?php

declare(strict_types=1);

namespace App\Modules\Tasks\Services;

use App\Modules\Notifications\Services\ReminderDeliveryService;
use App\Modules\Tasks\Models\TaskReminder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class TaskReminderDispatcher
{
    public function dispatch(): int
    {
        $sent = 0;
        TaskReminder::query()->where('status', 'pending')->where('remind_at', '<=', now())->orderBy('id')
            ->chunkById(100, function ($reminders) use (&$sent): void {
                foreach ($reminders as $reminder) {
                    $sent += DB::transaction(function () use ($reminder): int {
                        $locked = TaskReminder::query()->whereKey($reminder->id)->lockForUpdate()->with(['user', 'task'])->first();
                        if ($locked === null || $locked->status !== 'pending' || $locked->remind_at->isFuture()
                            || $locked->task === null || $locked->task->isFinalized() || $locked->user === null
                            || ! $locked->user->isActive() || ! Gate::forUser($locked->user)->allows('view', $locked->task)) {
                            return 0;
                        }

                        return (int) app(ReminderDeliveryService::class)->sendOnce(
                            $locked->user, 'task:'.$locked->id.':'.$locked->remind_at->format('Y-m-d H:i:s'),
                            ['type' => 'task_reminder', 'title' => 'یادآوری دستورکار', 'message' => $locked->title,
                                'task_id' => (int) $locked->task_id, 'url' => route('tasks.show', $locked->task)],
                        );
                    });
                }
            });

        return $sent;
    }
}
