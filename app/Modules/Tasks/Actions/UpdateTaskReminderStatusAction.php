<?php

declare(strict_types=1);

namespace App\Modules\Tasks\Actions;

use App\Models\User;
use App\Modules\Tasks\Models\TaskReminder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class UpdateTaskReminderStatusAction
{
    public function execute(
        TaskReminder $reminder,
        string $status,
        User $actor,
    ): TaskReminder {
        if (! in_array($status, ['completed', 'cancelled'], true)) {
            throw new InvalidArgumentException('وضعیت یادآور نامعتبر است.');
        }

        if ($reminder->status !== 'pending') {
            throw new InvalidArgumentException(
                'فقط یادآورهای در انتظار قابل تغییر هستند.'
            );
        }

        return DB::transaction(function () use ($reminder, $status): TaskReminder {
            $reminder->update([
                'status' => $status,
                'completed_at' => $status === 'completed' ? now() : null,
            ]);

            return $reminder->refresh()->load(['task', 'user', 'creator']);
        });
    }
}
