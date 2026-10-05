<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Tasks;

use App\Models\User;
use App\Modules\Tasks\Models\Task;
use App\Modules\Tasks\Models\TaskAssignment;
use App\Modules\Tasks\Models\TaskComment;
use App\Modules\Tasks\Models\TaskReminder;
use App\Modules\Tasks\Models\TaskStatusHistory;
use App\Modules\Tasks\Models\TaskWorkLog;
use App\Modules\Tasks\Services\TaskTimelineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskTimelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_timeline_contains_assignment_status_work_log_comment_and_reminder(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $task = Task::query()->create([
            'project_id' => null,
            'department_id' => null,
            'title' => 'Timeline Test Task',
            'created_by' => $user->id,
            'assignee_id' => $user->id,
            'status' => 'in_progress',
            'priority' => 'normal',
            'is_active' => true,
        ]);

        $now = now();

        TaskAssignment::query()->create([
            'task_id' => $task->id,
            'from_user_id' => null,
            'to_user_id' => $user->id,
            'assigned_by' => $user->id,
            'type' => 'assignment',
            'started_at' => $now->copy()->subMinutes(10),
            'created_at' => $now->copy()->subMinutes(10),
            'updated_at' => $now->copy()->subMinutes(10),
        ]);

        TaskStatusHistory::query()->create([
            'task_id' => $task->id,
            'from_status' => 'todo',
            'to_status' => 'in_progress',
            'changed_by' => $user->id,
            'changed_at' => $now->copy()->subMinutes(8),
        ]);

        $workLog = TaskWorkLog::query()->create([
            'task_id' => $task->id,
            'user_id' => $user->id,
            'title' => 'پیگیری فنی',
            'description' => 'بررسی انجام شد.',
        ]);
        $workLog->forceFill([
            'created_at' => $now->copy()->subMinutes(6),
            'updated_at' => $now->copy()->subMinutes(6),
        ])->save();

        $comment = TaskComment::query()->create([
            'task_id' => $task->id,
            'user_id' => $user->id,
            'body' => 'نیاز به بررسی بیشتر دارد.',
        ]);
        $comment->forceFill([
            'created_at' => $now->copy()->subMinutes(4),
            'updated_at' => $now->copy()->subMinutes(4),
        ])->save();

        $reminder = TaskReminder::query()->create([
            'task_id' => $task->id,
            'user_id' => $user->id,
            'created_by' => $user->id,
            'title' => 'پیگیری نهایی',
            'description' => 'فردا بررسی شود.',
            'remind_at' => $now->copy()->addHour(),
            'status' => 'pending',
        ]);
        $reminder->forceFill([
            'created_at' => $now->copy()->subMinutes(2),
            'updated_at' => $now->copy()->subMinutes(2),
        ])->save();

        $timeline = app(TaskTimelineService::class)->build($task);

        $this->assertCount(5, $timeline);
        $this->assertSame(
            ['reminder', 'comment', 'work_log', 'status', 'assignment'],
            array_column($timeline, 'type'),
        );
        $this->assertSame('یادآور: پیگیری نهایی', $timeline[0]['title']);
        $this->assertSame('نظر', $timeline[1]['title']);
    }
}
