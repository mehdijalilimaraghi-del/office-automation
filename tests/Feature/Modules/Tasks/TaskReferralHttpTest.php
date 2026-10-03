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
