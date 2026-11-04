<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Tasks;

use App\Core\Permissions\Models\Permission;
use App\Core\Roles\Models\Role;
use App\Models\User;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectMember;
use App\Modules\Tasks\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TaskReferralHttpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\CorePermissionSeeder::class);
    }

    public function test_current_assignee_can_refer_task(): void
    {
        $current = $this->userWithPermission('tasks.refer');
        $newAssignee = User::factory()->create(['status' => 'active']);

        $project = Project::query()->create([
            'name' => 'Referral Test Project',
            'code' => 'RTP-'.Str::upper(Str::random(8)),
            'status' => 'active',
            'manager_id' => $current->id,
            'created_by' => $current->id,
            'is_active' => true,
        ]);

        foreach ([$current, $newAssignee] as $user) {
            ProjectMember::query()->create([
                'project_id' => $project->id,
                'user_id' => $user->id,
                'project_role_id' => null,
            ]);
        }

        $task = Task::query()->create([
            'project_id' => $project->id,
            'department_id' => null,
            'title' => 'Referral HTTP Task',
            'created_by' => $current->id,
            'assignee_id' => $current->id,
            'status' => 'in_progress',
            'priority' => 'normal',
            'is_active' => true,
        ]);

        $this->actingAs($current)
            ->patch(route('tasks.refer', $task), [
                'assignee_id' => $newAssignee->id,
                'note' => 'ادامه کار توسط همکار جدید',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'تسک با موفقیت ارجاع شد.');

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'assignee_id' => $newAssignee->id,
        ]);

        $this->assertDatabaseHas('task_assignments', [
            'task_id' => $task->id,
            'from_user_id' => $current->id,
            'to_user_id' => $newAssignee->id,
            'assigned_by' => $current->id,
            'type' => 'handoff',
            'note' => 'ادامه کار توسط همکار جدید',
        ]);
    }

    public function test_non_assignee_cannot_refer_task(): void
    {
        $current = User::factory()->create(['status' => 'active']);
        $other = $this->userWithPermission('tasks.refer');
        $newAssignee = User::factory()->create(['status' => 'active']);

        $task = Task::query()->create([
            'project_id' => null,
            'department_id' => null,
            'title' => 'Protected Referral Task',
            'created_by' => $current->id,
            'assignee_id' => $current->id,
            'status' => 'todo',
            'priority' => 'normal',
            'is_active' => true,
        ]);

        $this->actingAs($other)
            ->patch(route('tasks.refer', $task), [
                'assignee_id' => $newAssignee->id,
                'note' => 'نباید ارجاع شود',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'assignee_id' => $current->id,
        ]);
    }

    public function test_department_manager_can_accept_task_responsibility_and_history_is_recorded(): void
    {
        $manager = $this->userWithPermission('tasks.assign');
        $employee = User::factory()->create(['status' => 'active']);
        $department = \App\Core\Departments\Models\Department::query()->create([
            'name' => 'واحد پذیرش مسئولیت',
            'code' => 'OWN-'.Str::upper(Str::random(7)),
            'is_active' => true,
            'manager_id' => $manager->id,
        ]);
        $task = Task::query()->create([
            'project_id' => null,
            'department_id' => $department->id,
            'title' => 'Task responsibility acceptance',
            'created_by' => $employee->id,
            'assignee_id' => $employee->id,
            'status' => 'in_progress',
            'priority' => 'normal',
            'is_active' => true,
        ]);

        $this->actingAs($manager)
            ->post(route('tasks.accept-responsibility', $task))
            ->assertRedirect()
            ->assertSessionHas('success', 'مسئولیت دستورکار به شما واگذار شد.');

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'assignee_id' => $manager->id]);
        $this->assertDatabaseHas('task_assignments', [
            'task_id' => $task->id,
            'from_user_id' => $employee->id,
            'to_user_id' => $manager->id,
            'assigned_by' => $manager->id,
            'type' => 'manager_accept_responsibility',
        ]);
    }

    public function test_non_manager_cannot_accept_task_responsibility(): void
    {
        $manager = User::factory()->create(['status' => 'active']);
        $other = $this->userWithPermission('tasks.assign');
        $department = \App\Core\Departments\Models\Department::query()->create([
            'name' => 'واحد حفاظت مسئولیت',
            'code' => 'DEN-'.Str::upper(Str::random(7)),
            'is_active' => true,
            'manager_id' => $manager->id,
        ]);
        $task = Task::query()->create([
            'project_id' => null,
            'department_id' => $department->id,
            'title' => 'Protected responsibility task',
            'created_by' => $manager->id,
            'assignee_id' => $manager->id,
            'status' => 'todo',
            'priority' => 'normal',
            'is_active' => true,
        ]);

        $this->actingAs($other)
            ->post(route('tasks.accept-responsibility', $task))
            ->assertForbidden();
    }

    public function test_finalized_and_review_locked_tasks_cannot_be_accepted_by_manager(): void
    {
        $manager = $this->userWithPermission('tasks.assign');
        $department = \App\Core\Departments\Models\Department::query()->create([
            'name' => 'واحد وضعیت نهایی',
            'code' => 'FIN-'.Str::upper(Str::random(7)),
            'is_active' => true,
            'manager_id' => $manager->id,
        ]);

        foreach ([['status' => 'completed', 'review_status' => 'approved'], ['status' => 'in_progress', 'review_status' => 'pending']] as $index => $state) {
            $task = Task::query()->create([
                'project_id' => null,
                'department_id' => $department->id,
                'title' => 'Locked responsibility task '.$index,
                'created_by' => $manager->id,
                'assignee_id' => User::factory()->create(['status' => 'active'])->id,
                'status' => $state['status'],
                'review_status' => $state['review_status'],
                'priority' => 'normal',
                'is_active' => true,
            ]);
            $this->actingAs($manager)->post(route('tasks.accept-responsibility', $task))->assertForbidden();
        }
    }

    private function userWithPermission(string $permissionCode): User
    {
        $permission = Permission::query()->where('code', $permissionCode)->firstOrFail();

        $role = Role::query()->create([
            'name' => 'Referral Test Role '.Str::upper(Str::random(8)),
            'code' => 'referral-'.Str::lower(Str::random(12)),
            'description' => 'Test role',
            'is_active' => true,
            'is_system' => false,
        ]);

        $role->permissions()->attach($permission->id);

        $user = User::factory()->create(['status' => 'active']);
        $user->roles()->attach($role->id);

        return $user->fresh();
    }
}
