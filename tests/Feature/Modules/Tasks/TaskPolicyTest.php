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

class TaskPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_member_with_view_permission_can_view_task(): void
    {
        $member = $this->userWithPermission('tasks.view');

        $project = $this->createProject($member);
        $task = $this->createTask($project, $member);

        $this->assertTrue(
            $member->can('view', $task)
        );
    }

    public function test_non_project_member_cannot_view_task(): void
    {
        $manager = User::factory()->create([
            'status' => 'active',
        ]);

        $user = $this->userWithPermission('tasks.view');

        $project = $this->createProject($manager);
        $task = $this->createTask($project, $manager);

        $this->assertFalse(
            $user->can('view', $task)
        );
    }

    public function test_project_manager_can_assign_task(): void
    {
        $manager = $this->userWithPermission('tasks.assign');

        $project = $this->createProject($manager);
        $task = $this->createTask($project, $manager);

        $this->assertTrue(
            $manager->can('assign', $task)
        );
    }

    public function test_non_manager_cannot_assign_task(): void
    {
        $manager = User::factory()->create([
            'status' => 'active',
        ]);

        $member = $this->userWithPermission('tasks.assign');

        $project = $this->createProject($manager, $member);
        $task = $this->createTask($project, $manager);

        $this->assertFalse(
            $member->can('assign', $task)
        );
    }

    public function test_assignee_can_change_task_status(): void
    {
        $manager = User::factory()->create([
            'status' => 'active',
        ]);

        $assignee = $this->userWithPermission('tasks.change_status');

        $project = $this->createProject($manager, $assignee);
        $task = $this->createTask($project, $manager, $assignee);

        $this->assertTrue(
            $assignee->can('changeStatus', $task)
        );
    }

    public function test_non_assignee_member_cannot_change_task_status(): void
    {
        $manager = User::factory()->create([
            'status' => 'active',
        ]);

        $member = $this->userWithPermission('tasks.change_status');
        $assignee = User::factory()->create([
            'status' => 'active',
        ]);

        $project = $this->createProject($manager, $member);
        $this->addMember($project, $assignee);

        $task = $this->createTask($project, $manager, $assignee);

        $this->assertFalse(
            $member->can('changeStatus', $task)
        );
    }

    public function test_task_creator_with_permission_can_update_task(): void
    {
        $creator = $this->userWithPermission('tasks.update');

        $project = $this->createProject($creator);
        $task = $this->createTask($project, $creator);

        $this->assertTrue(
            $creator->can('update', $task)
        );
    }

    public function test_user_without_permission_cannot_update_task(): void
    {
        $creator = User::factory()->create([
            'status' => 'active',
        ]);

        $project = $this->createProject($creator);
        $task = $this->createTask($project, $creator);

        $this->assertFalse(
            $creator->can('update', $task)
        );
    }

    public function test_inactive_user_cannot_access_task(): void
    {
        $user = $this->userWithPermission('tasks.view');

        $user->update([
            'status' => 'inactive',
        ]);

        $project = $this->createProject($user);
        $task = $this->createTask($project, $user);

        $this->assertFalse(
            $user->can('view', $task)
        );
    }

    private function userWithPermission(string $permissionCode): User
    {
        $permission = Permission::query()
            ->where('code', $permissionCode)
            ->firstOrFail();

        $role = Role::query()->create([
            'name' => 'Task Policy Test Role ' . Str::upper(Str::random(8)),
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

    private function createProject(
        User $manager,
        ?User $additionalMember = null
    ): Project {
        $project = Project::query()->create([
            'name' => 'Task Policy Test Project',
            'code' => 'TPL-' . Str::upper(Str::random(8)),
            'status' => 'active',
            'manager_id' => $manager->id,
            'created_by' => $manager->id,
            'is_active' => true,
        ]);

        $this->addMember($project, $manager);

        if ($additionalMember !== null) {
            $this->addMember($project, $additionalMember);
        }

        return $project;
    }

    private function addMember(Project $project, User $user): void
    {
        ProjectMember::query()->create([
            'project_id' => $project->id,
            'user_id' => $user->id,
            'project_role_id' => null,
        ]);
    }

private function createTask(
    Project $project,
    User $creator,
    ?User $assignee = null
): Task {
    $assignee ??= $creator;

    return Task::query()->create([
        'project_id' => $project->id,
        'title' => 'Policy Test Task',
        'description' => 'Task created for policy testing.',
        'created_by' => $creator->id,
        'assignee_id' => $assignee->id,
        'status' => 'todo',
        'priority' => 'normal',
    ]);
}
}