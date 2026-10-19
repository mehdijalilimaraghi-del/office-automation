<?php

declare(strict_types=1);

namespace App\Modules\Tasks\Services;

use App\Modules\Tasks\Models\Task;
use Illuminate\Support\Collection;

class TaskTimelineService
{
    public function build(Task $task): array
    {
        $task->loadMissing([
            'assignments.fromUser',
            'assignments.toUser',
            'assignments.assignedBy',
            'comments.user',
            'activities.creator',
            'activities.completedBy',
            'reminders.user',
            'reminders.creator',
            'statusHistory.changedBy',
        ]);

        $timeline = collect();

        foreach ($task->assignments as $item) {
            $timeline->push([
                'type' => 'assignment',
                'title' => $item->type === 'handoff'
                    ? 'ارجاع تسک'
                    : 'تخصیص تسک',
                'description' => $item->note,
                'occurred_at' => $item->started_at,
                'user' => $item->assignedBy,
                'meta' => [
                    'from_user' => $item->fromUser,
                    'to_user' => $item->toUser,
                    'assignment_type' => $item->type,
                    'ended_at' => $item->ended_at,
                ],
            ]);
        }

        foreach ($task->statusHistory as $item) {
            $timeline->push([
                'type' => 'status',
                'title' => 'تغییر وضعیت',
                'description' => $this->statusLabel($item->from_status)
                    .' ← '
                    .$this->statusLabel($item->to_status),
                'occurred_at' => $item->changed_at,
                'user' => $item->changedBy,
                'meta' => [
                    'from_status' => $item->from_status,
                    'to_status' => $item->to_status,
                    'note' => $item->note,
                ],
            ]);
        }

        foreach ($task->activities->where('activity_type', 'logged') as $item) {
            $timeline->push([
                'id' => $item->id,
                'type' => 'work_log',
                'title' => $item->title,
                'description' => $item->description,
                'occurred_at' => $item->completed_at ?? $item->created_at,
                'user' => $item->completedBy,
                'meta' => [
                    'started_at' => $item->started_at,
                    'completed_at' => $item->completed_at,
                ],
            ]);
        }

        foreach ($task->comments as $item) {
            $timeline->push([
                'type' => 'comment',
                'title' => 'نظر',
                'description' => $item->body,
                'occurred_at' => $item->created_at,
                'user' => $item->user,
                'meta' => [],
            ]);
        }

        foreach ($task->reminders as $item) {
            $timeline->push([
                'type' => 'reminder',
                'title' => 'یادآور: '.$item->title,
                'description' => $item->description,
                'occurred_at' => $item->created_at,
                'user' => $item->creator,
                'meta' => [
                    'remind_at' => $item->remind_at,
                    'status' => $item->status,
                    'completed_at' => $item->completed_at,
                    'recipient' => $item->user,
                ],
            ]);
        }

        /** @var Collection<int, array<string, mixed>> $timeline */
        return $timeline
            ->sortByDesc(fn (array $item) => $item['occurred_at'])
            ->values()
            ->all();
    }

    private function statusLabel(?string $status): string
    {
        return match ($status) {
            'todo' => 'انجام نشده',
            'observed' => 'مشاهده شده',
            'in_progress' => 'در حال انجام',
            'completed' => 'تکمیل شده',
            'cancelled' => 'لغو شده',
            'cannot_continue' => 'امکان ادامه وجود ندارد',
            'submitted' => 'در حال بررسی مدیر',
            null => '—',
            default => $status,
        };
    }
}
