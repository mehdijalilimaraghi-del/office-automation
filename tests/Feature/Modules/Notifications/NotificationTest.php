<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Notifications;

use App\Core\Departments\Models\Department;
use App\Models\User;
use App\Modules\Tasks\Actions\AssignTaskAction;
use App\Modules\Tasks\Actions\ReviewTaskAction;
use App\Modules\Tasks\Actions\SubmitTaskAction;
use App\Modules\Tasks\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\CorePermissionSeeder::class);
    }

    public function test_task_assignment_creates_database_notification_for_assignee(): void
    {
        $actor = User::factory()->create(['status' => 'active']);
        $assignee = User::factory()->create(['status' => 'active']);

        $task = Task::query()->create([
            'title' => 'Assigned task',
            'status' => 'todo',
            'priority' => 'normal',
            'assignee_id' => $actor->id,
            'created_by' => $actor->id,
        ]);

        app(AssignTaskAction::class)->execute($task, $assignee, $actor);

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => User::class,
            'notifiable_id' => $assignee->id,
        ]);

        $this->assertSame(1, $assignee->fresh()->unreadNotifications()->count());
    }

    public function test_submission_notifies_department_manager(): void
    {
        $manager = User::factory()->create(['status' => 'active']);
        $assignee = User::factory()->create(['status' => 'active']);

        $department = Department::query()->create([
            'name' => 'Notification Unit',
            'code' => 'NOT-'.Str::upper(Str::random(6)),
            'manager_id' => $manager->id,
            'is_active' => true,
        ]);

        $task = Task::query()->create([
            'department_id' => $department->id,
            'title' => 'Pending approval task',
            'status' => 'in_progress',
            'priority' => 'normal',
            'review_required' => true,
            'review_status' => 'not_started',
            'assignee_id' => $assignee->id,
            'created_by' => $manager->id,
        ]);

        app(SubmitTaskAction::class)->execute($task, $assignee);

        $this->assertSame(1, $manager->fresh()->unreadNotifications()->count());
    }

    public function test_return_notifies_assignee_and_approval_notifies_creator(): void
    {
        $manager = User::factory()->create(['status' => 'active']);
        $assignee = User::factory()->create(['status' => 'active']);
        $creator = User::factory()->create(['status' => 'active']);

        $department = Department::query()->create([
            'name' => 'Lifecycle Notification Unit',
            'code' => 'LNT-'.Str::upper(Str::random(6)),
            'manager_id' => $manager->id,
            'is_active' => true,
        ]);

        $task = Task::query()->create([
            'department_id' => $department->id,
            'title' => 'Lifecycle task',
            'status' => 'submitted',
            'priority' => 'normal',
            'review_required' => true,
            'review_status' => 'pending',
            'assignee_id' => $assignee->id,
            'created_by' => $creator->id,
            'review_requested_at' => now(),
        ]);

        app(ReviewTaskAction::class)->returnTask($task, $manager, 'نیاز به اصلاح دارد.');

        $this->assertSame(1, $assignee->fresh()->unreadNotifications()->count());

        $task->update([
            'status' => 'submitted',
            'review_status' => 'pending',
            'review_requested_at' => now(),
        ]);

        app(ReviewTaskAction::class)->approve($task, $manager);

        $this->assertSame(1, $creator->fresh()->unreadNotifications()->count());
    }

    public function test_user_can_mark_notification_as_read(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $user->notify(new \App\Modules\Notifications\Notifications\TaskNotification(
            'task_assigned',
            'اعلان',
            'تست اعلان',
            Task::query()->create([
                'title' => 'Notification task',
                'status' => 'todo',
                'priority' => 'normal',
                'assignee_id' => $user->id,
                'created_by' => $user->id,
            ]),
        ));

        $notification = $user->fresh()->unreadNotifications()->firstOrFail();
        $notification->markAsRead();

        $this->assertSame(0, $user->fresh()->unreadNotifications()->count());
        $this->assertNotNull($notification->fresh()->read_at);
    }
}