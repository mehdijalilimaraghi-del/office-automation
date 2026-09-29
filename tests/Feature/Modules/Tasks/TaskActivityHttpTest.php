<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Tasks;

use App\Core\Permissions\Models\Permission;
use App\Core\Roles\Models\Role;
use App\Models\User;
use App\Modules\Tasks\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TaskActivityHttpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\CorePermissionSeeder::class);
    }

    public function test_authorized_user_can_create_comment(): void
    {
        $user = $this->userWithPermission('tasks.comment');
        $task = $this->createTask($user);

        $this->actingAs($user)
            ->post(route('tasks.comments.store', $task), [
                'body' => 'کامنت ثبت شده از طریق HTTP',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'کامنت با موفقیت ثبت شد.');

        $this->assertDatabaseHas('task_comments', [
            'task_id' => $task->id,
            'user_id' => $user->id,
            'body' => 'کامنت ثبت شده از طریق HTTP',
        ]);
    }

    public function test_user_without_comment_permission_is_forbidden(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $task = $this->createTask($user);

        $this->actingAs($user)
            ->post(route('tasks.comments.store', $task), [
                'body' => 'نباید ثبت شود',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('task_comments', [
            'task_id' => $task->id,
            'body' => 'نباید ثبت شود',
        ]);
    }

    public function test_comment_requires_body(): void
    {
        $user = $this->userWithPermission('tasks.comment');
        $task = $this->createTask($user);

        $this->actingAs($user)
            ->post(route('tasks.comments.store', $task), [])
            ->assertSessionHasErrors('body');
    }

    public function test_authorized_user_can_create_work_log(): void
    {
        $user = $this->userWithPermission('tasks.log_work');
        $task = $this->createTask($user);

        $this->actingAs($user)
            ->post(route('tasks.work-logs.store', $task), [
                'title' => 'پیگیری مالی',
                'description' => 'تماس با واحد مالی',
                'started_at' => '2026-09-29 10:00:00',
                'completed_at' => '2026-09-29 11:00:00',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'گزارش فعالیت با موفقیت ثبت شد.');

        $this->assertDatabaseHas('task_work_logs', [
            'task_id' => $task->id,
            'user_id' => $user->id,
            'title' => 'پیگیری مالی',
            'description' => 'تماس با واحد مالی',
        ]);
    }

    public function test_authorized_user_can_create_reminder(): void
    {
        $creator = $this->userWithPermission('tasks.create_reminder');
        $recipient = User::factory()->create(['status' => 'active']);
        $task = $this->createTask($creator);

        $this->actingAs($creator)
            ->post(route('tasks.reminders.store', $task), [
                'user_id' => $recipient->id,
                'title' => 'پیگیری قرارداد',
                'description' => 'قرارداد بررسی شود.',
                'remind_at' => '2026-09-30 09:00:00',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'یادآور با موفقیت ایجاد شد.');

        $this->assertDatabaseHas('task_reminders', [
            'task_id' => $task->id,
            'user_id' => $recipient->id,
            'created_by' => $creator->id,
            'title' => 'پیگیری قرارداد',
            'status' => 'pending',
        ]);
    }

    public function test_user_without_reminder_permission_is_forbidden(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $recipient = User::factory()->create(['status' => 'active']);
        $task = $this->createTask($user);

        $this->actingAs($user)
            ->post(route('tasks.reminders.store', $task), [
                'user_id' => $recipient->id,
                'title' => 'نباید ثبت شود',
                'remind_at' => '2026-09-30 09:00:00',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('task_reminders', [
            'task_id' => $task->id,
            'title' => 'نباید ثبت شود',
        ]);
    }

    private function userWithPermission(string $permissionCode): User
    {
        $permission = Permission::query()
            ->where('code', $permissionCode)
            ->firstOrFail();

        $role = Role::query()->create([
            'name' => 'Task Activity HTTP Test Role ' . Str::upper(Str::random(8)),
            'code' => 'test-' . Str::lower(Str::random(12)),
            'description' => 'Test role',
            'is_active' => true,
            'is_system' => false,
        ]);

        $role->permissions()->attach($permission->id);

        $user = User::factory()->create([
            'status' => 'active',
        ]);

        $user->roles()->attach($role->id);

        return $user->fresh();
    }

    private function createTask(User $creator): Task
    {
        return Task::query()->create([
            'project_id' => null,
            'department_id' => null,
            'title' => 'HTTP Activity Test Task',
            'description' => 'Task created for HTTP activity testing.',
            'created_by' => $creator->id,
            'assignee_id' => $creator->id,
            'status' => 'todo',
            'priority' => 'normal',
            'is_active' => true,
        ]);
    }
}
