<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Tasks;

use App\Core\Permissions\Models\Permission;
use App\Core\Roles\Models\Role;
use App\Models\User;
use App\Modules\Tasks\Models\Task;
use App\Modules\Tasks\Models\TaskReminder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TaskReminderLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\CorePermissionSeeder::class);
    }

    public function test_recipient_can_complete_pending_reminder(): void
    {
        $recipient = $this->userWithPermission('tasks.view');
        $task = $this->createTask($recipient);

        $reminder = TaskReminder::query()->create([
            'task_id' => $task->id,
            'user_id' => $recipient->id,
            'created_by' => $recipient->id,
            'title' => 'پیگیری',
            'remind_at' => now()->addHour(),
            'status' => 'pending',
        ]);

        $this->actingAs($recipient)
            ->patch(route('tasks.reminders.update-status', [$task, $reminder]), [
                'status' => 'completed',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'یادآور تکمیل شد.');

        $reminder->refresh();

        $this->assertSame('completed', $reminder->status);
        $this->assertNotNull($reminder->completed_at);
    }

    public function test_recipient_can_cancel_pending_reminder(): void
    {
        $recipient = $this->userWithPermission('tasks.view');
        $task = $this->createTask($recipient);

        $reminder = TaskReminder::query()->create([
            'task_id' => $task->id,
            'user_id' => $recipient->id,
            'created_by' => $recipient->id,
            'title' => 'پیگیری',
            'remind_at' => now()->addHour(),
            'status' => 'pending',
        ]);

        $this->actingAs($recipient)
            ->patch(route('tasks.reminders.update-status', [$task, $reminder]), [
                'status' => 'cancelled',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'یادآور لغو شد.');

        $this->assertDatabaseHas('task_reminders', [
            'id' => $reminder->id,
            'status' => 'cancelled',
            'completed_at' => null,
        ]);
    }

    public function test_non_recipient_cannot_complete_reminder(): void
    {
        $recipient = User::factory()->create(['status' => 'active']);
        $other = $this->userWithPermission('tasks.view');
        $task = $this->createTask($recipient);

        $reminder = TaskReminder::query()->create([
            'task_id' => $task->id,
            'user_id' => $recipient->id,
            'created_by' => $other->id,
            'title' => 'پیگیری',
            'remind_at' => now()->addHour(),
            'status' => 'pending',
        ]);

        $this->actingAs($other)
            ->patch(route('tasks.reminders.update-status', [$task, $reminder]), [
                'status' => 'completed',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('task_reminders', [
            'id' => $reminder->id,
            'status' => 'pending',
        ]);
    }

    public function test_completed_reminder_cannot_be_changed_again(): void
    {
        $recipient = $this->userWithPermission('tasks.view');
        $task = $this->createTask($recipient);

        $reminder = TaskReminder::query()->create([
            'task_id' => $task->id,
            'user_id' => $recipient->id,
            'created_by' => $recipient->id,
            'title' => 'پیگیری',
            'remind_at' => now()->addHour(),
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        $this->actingAs($recipient)
            ->patch(route('tasks.reminders.update-status', [$task, $reminder]), [
                'status' => 'cancelled',
            ])
            ->assertSessionHasErrors('status');

        $this->assertDatabaseHas('task_reminders', [
            'id' => $reminder->id,
            'status' => 'completed',
        ]);
    }

    private function userWithPermission(string $permissionCode): User
    {
        $permission = Permission::query()->where('code', $permissionCode)->firstOrFail();

        $role = Role::query()->create([
            'name' => 'Reminder Test Role '.Str::upper(Str::random(8)),
            'code' => 'reminder-'.Str::lower(Str::random(12)),
            'description' => 'Test role',
            'is_active' => true,
            'is_system' => false,
        ]);

        $role->permissions()->attach($permission->id);

        $user = User::factory()->create(['status' => 'active']);
        $user->roles()->attach($role->id);

        return $user->fresh();
    }

    private function createTask(User $user): Task
    {
        return Task::query()->create([
            'project_id' => null,
            'department_id' => null,
            'title' => 'Reminder Test Task',
            'created_by' => $user->id,
            'assignee_id' => $user->id,
            'status' => 'todo',
            'priority' => 'normal',
            'is_active' => true,
        ]);
    }
}
