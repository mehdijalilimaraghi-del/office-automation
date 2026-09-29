<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Tasks;

use App\Models\User;
use App\Modules\Tasks\Actions\CreateTaskCommentAction;
use App\Modules\Tasks\Actions\CreateTaskReminderAction;
use App\Modules\Tasks\Actions\CreateTaskWorkLogAction;
use App\Modules\Tasks\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskActivityTest extends TestCase
{
    use RefreshDatabase;

    private function createTask(User $user): Task
    {
        return Task::query()->create([
            'project_id' => null,
            'department_id' => null,
            'title' => 'Activity Test Task',
            'created_by' => $user->id,
            'assignee_id' => $user->id,
            'status' => 'todo',
            'priority' => 'normal',
        ]);
    }

    public function test_comment_is_created_with_author(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $task = $this->createTask($user);

        $comment = app(CreateTaskCommentAction::class)->execute(
            task: $task,
            author: $user,
            body: 'اولین کامنت تسک',
        );

        $this->assertDatabaseHas('task_comments', [
            'id' => $comment->id,
            'task_id' => $task->id,
            'user_id' => $user->id,
            'body' => 'اولین کامنت تسک',
        ]);
        $this->assertTrue($comment->relationLoaded('user'));
    }

    public function test_work_log_is_created_with_user_and_dates(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $task = $this->createTask($user);
        $startedAt = now()->subHour();
        $completedAt = now();

        $log = app(CreateTaskWorkLogAction::class)->execute(
            task: $task,
            user: $user,
            title: 'پیگیری مالی',
            description: 'تماس و پیگیری با واحد مالی',
            startedAt: $startedAt,
            completedAt: $completedAt,
        );

        $this->assertDatabaseHas('task_work_logs', [
            'id' => $log->id,
            'task_id' => $task->id,
            'user_id' => $user->id,
            'title' => 'پیگیری مالی',
            'description' => 'تماس و پیگیری با واحد مالی',
        ]);
        $this->assertTrue($log->relationLoaded('user'));
        $this->assertNotNull($log->started_at);
        $this->assertNotNull($log->completed_at);
    }

    public function test_reminder_is_created_for_recipient_and_creator(): void
    {
        $creator = User::factory()->create(['status' => 'active']);
        $recipient = User::factory()->create(['status' => 'active']);
        $task = $this->createTask($creator);

        $remindAt = now()->addDay();

        $reminder = app(CreateTaskReminderAction::class)->execute(
            task: $task,
            recipient: $recipient,
            creator: $creator,
            title: 'پیگیری قرارداد',
            remindAt: $remindAt,
            description: 'فردا قرارداد بررسی شود.',
        );

        $this->assertDatabaseHas('task_reminders', [
            'id' => $reminder->id,
            'task_id' => $task->id,
            'user_id' => $recipient->id,
            'created_by' => $creator->id,
            'title' => 'پیگیری قرارداد',
            'status' => 'pending',
        ]);
        $this->assertTrue($reminder->relationLoaded('user'));
        $this->assertTrue($reminder->relationLoaded('creator'));
    }
}
