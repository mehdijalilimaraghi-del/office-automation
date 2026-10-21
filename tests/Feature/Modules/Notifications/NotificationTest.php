<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Notifications;

use App\Core\Departments\Models\Department;
use App\Core\Permissions\Models\Permission;
use App\Core\Roles\Models\Role;
use App\Models\User;
use App\Modules\Tasks\Actions\AssignTaskAction;
use App\Modules\Tasks\Actions\ReviewTaskAction;
use App\Modules\Tasks\Actions\CreateTaskCommentAction;
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

    public function test_any_authenticated_user_can_open_their_own_notification_center(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $this->actingAs($user)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Notifications/Index')
                ->where('unreadCount', 0));
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
        $this->assertSame(
            'دستورکار جدید به شما تخصیص یافت',
            $assignee->fresh()->unreadNotifications()->firstOrFail()->data['title'],
        );
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
        $this->assertSame(
            'دستورکار در انتظار تأیید شماست',
            $manager->fresh()->unreadNotifications()->firstOrFail()->data['title'],
        );
    }

    public function test_return_notifies_assignee_and_approval_notifies_creator(): void
    {
        $manager = $this->userWithPermission('tasks.review');
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
            'status' => 'in_progress',
            'sub_status' => 'manager_review',
            'priority' => 'normal',
            'review_required' => true,
            'review_status' => 'pending',
            'assignee_id' => $assignee->id,
            'created_by' => $creator->id,
            'review_requested_at' => now(),
        ]);

        app(ReviewTaskAction::class)->returnTask($task, $manager, 'نیاز به اصلاح دارد.');

        $this->assertSame(1, $assignee->fresh()->unreadNotifications()->count());
        $this->assertSame(
            'دستورکار برای اصلاح بازگردانده شد',
            $assignee->fresh()->unreadNotifications()->firstOrFail()->data['title'],
        );

        $task->update([
            'status' => 'in_progress',
            'sub_status' => 'manager_review',
            'review_status' => 'pending',
            'review_requested_at' => now(),
        ]);

        app(ReviewTaskAction::class)->approve($task, $manager);

        $this->assertSame(1, $creator->fresh()->unreadNotifications()->count());
        $this->assertSame(
            'دستورکار تأیید و تکمیل شد',
            $creator->fresh()->unreadNotifications()->firstOrFail()->data['title'],
        );
    }

    public function test_new_task_comment_notifies_current_manager_and_assignee_except_author(): void
    {
        $creator = User::factory()->create(['status' => 'active']);
        $assignee = User::factory()->create(['status' => 'active']);
        $author = User::factory()->create(['status' => 'active']);

        $department = Department::query()->create([
            'name' => 'Task Comment Notification Unit',
            'code' => 'TCN-'.Str::upper(Str::random(6)),
            'manager_id' => $creator->id,
            'is_active' => true,
        ]);

        $task = Task::query()->create([
            'department_id' => $department->id,
            'title' => 'Task with new comment',
            'status' => 'in_progress',
            'priority' => 'normal',
            'assignee_id' => $assignee->id,
            'created_by' => $creator->id,
        ]);

        app(CreateTaskCommentAction::class)->execute($task, $author, 'لطفاً این مورد را بررسی کنید.');

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => User::class,
            'notifiable_id' => $creator->id,
        ]);
        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => User::class,
            'notifiable_id' => $assignee->id,
        ]);
        $this->assertSame(0, $author->fresh()->unreadNotifications()->count());
        $this->assertSame(
            'task_commented',
            $creator->fresh()->unreadNotifications()->firstOrFail()->data['type'],
        );
    }

    public function test_new_comment_notifies_only_current_manager_and_assignee_not_previous_assignees(): void
    {
        $manager = User::factory()->create(['status' => 'active']);
        $currentAssignee = User::factory()->create(['status' => 'active']);
        $previousAssignee = User::factory()->create(['status' => 'active']);
        $author = User::factory()->create(['status' => 'active']);
        $department = Department::query()->create([
            'name' => 'Comment Recipients Unit',
            'code' => 'CRU-'.Str::upper(Str::random(6)),
            'manager_id' => $manager->id,
            'is_active' => true,
        ]);
        $task = Task::query()->create([
            'department_id' => $department->id,
            'title' => 'Current assignee comment task',
            'status' => 'in_progress',
            'priority' => 'normal',
            'assignee_id' => $currentAssignee->id,
            'created_by' => $previousAssignee->id,
        ]);

        app(CreateTaskCommentAction::class)->execute($task, $author, 'دیدگاه جدید');

        $this->assertSame(1, $manager->fresh()->unreadNotifications()->count());
        $this->assertSame(1, $currentAssignee->fresh()->unreadNotifications()->count());
        $this->assertSame(0, $previousAssignee->fresh()->unreadNotifications()->count());
        $this->assertSame(0, $author->fresh()->unreadNotifications()->count());
    }

    public function test_direct_comment_reply_is_saved_with_parent_comment(): void
    {
        $author = User::factory()->create(['status' => 'active']);
        $replyAuthor = User::factory()->create(['status' => 'active']);
        $task = Task::query()->create([
            'title' => 'Reply task',
            'status' => 'in_progress',
            'priority' => 'normal',
            'assignee_id' => $author->id,
            'created_by' => $author->id,
        ]);

        $parent = app(CreateTaskCommentAction::class)->execute($task, $author, 'دیدگاه اصلی');
        $reply = app(CreateTaskCommentAction::class)->execute($task, $replyAuthor, 'پاسخ', $parent->id);

        $this->assertSame($parent->id, $reply->parent_comment_id);
        $this->assertSame('دیدگاه اصلی', $reply->parent->body);
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
    private function userWithPermission(string $permissionCode): User
    {
        $permission = Permission::query()->where('code', $permissionCode)->firstOrFail();

        $role = Role::query()->create([
            'name' => 'Notification Test Role '.Str::upper(Str::random(8)),
            'code' => 'notification-'.Str::lower(Str::random(12)),
            'description' => 'Notification lifecycle test role',
            'is_active' => true,
            'is_system' => false,
        ]);

        $role->permissions()->attach($permission->id);

        $user = User::factory()->create(['status' => 'active']);
        $user->roles()->attach($role->id);

        return $user->fresh();
    }

}