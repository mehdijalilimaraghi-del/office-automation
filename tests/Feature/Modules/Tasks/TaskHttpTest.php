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

class TaskHttpTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_member_can_create_task_through_http_layer(): void
    {
        $creator = $this->userWithPermission('tasks.create');
        $project = $this->createProject($creator);
        $assignee = User::factory()->create();

        $this->addMember($project, $assignee);

        $response = $this->actingAs($creator)->post(
            route('tasks.store'),
            [
                'project_id' => $project->id,
                'title' => 'HTTP Test Task',
                'description' => 'Created through HTTP layer.',
                'assignee_id' => $assignee->id,
                'priority' => 'high',
            ]
        );

        $response->assertRedirect();

        $this->assertDatabaseHas('tasks', [
            'project_id' => $project->id,
            'title' => 'HTTP Test Task',
            'assignee_id' => $assignee->id,
            'created_by' => $creator->id,
            'priority' => 'high',
        ]);
    }

    public function test_non_project_member_cannot_create_task(): void
    {
        $user = $this->userWithPermission('tasks.create');
        $manager = User::factory()->create();
        $project = $this->createProject($manager);
        $assignee = User::factory()->create();

        $this->addMember($project, $assignee);

        $this->actingAs($user)
            ->post(route('tasks.store'), [
                'project_id' => $project->id,
                'title' => 'Forbidden Task',
                'assignee_id' => $assignee->id,
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('tasks', [
            'title' => 'Forbidden Task',
        ]);
    }

    public function test_task_creator_can_update_task(): void
    {
        $creator = $this->userWithPermission('tasks.update');
        $project = $this->createProject($creator);
        $task = $this->createTask($project, $creator);

        $response = $this->actingAs($creator)->put(
            route('tasks.update', $task),
            [
                'title' => 'Updated HTTP Task',
                'description' => 'Updated description.',
                'priority' => 'urgent',
            ]
        );

        $response->assertRedirect();

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'title' => 'Updated HTTP Task',
            'priority' => 'urgent',
        ]);
    }

    public function test_assignee_can_change_task_status(): void
    {
        $assignee = $this->userWithPermission('tasks.change_status');
        $project = $this->createProject($assignee);
        $task = $this->createTask($project, $assignee);

        $response = $this->actingAs($assignee)->patch(
            route('tasks.change-status', $task),
            [
                'status' => 'completed',
            ]
        );

        $response->assertRedirect();

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'status' => 'completed',
        ]);

        $this->assertNotNull(
            $task->refresh()->completed_at
        );
    }

    public function test_project_manager_can_assign_task_through_http_layer(): void
    {
        $manager = $this->userWithPermission('tasks.assign');
        $project = $this->createProject($manager);
        $assignee = User::factory()->create([
            'status' => 'active',
        ]);

        $this->addMember($project, $assignee);

        $task = $this->createTask($project, $manager);

        $this->actingAs($manager)
            ->patch(route('tasks.assign', $task), [
                'assignee_id' => $assignee->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'assignee_id' => $assignee->id,
        ]);
    }

    public function test_project_manager_can_delete_task(): void
    {
        $manager = $this->userWithPermission('tasks.delete');
        $project = $this->createProject($manager);
        $task = $this->createTask($project, $manager);

        $this->actingAs($manager)
            ->delete(route('tasks.destroy', $task))
            ->assertRedirect();

        $this->assertDatabaseMissing('tasks', [
            'id' => $task->id,
        ]);
    }

    private function userWithPermission(string $permissionCode): User
    {
        $permission = Permission::query()
            ->where('code', $permissionCode)
            ->firstOrFail();

        $role = Role::query()->create([
            'name' => 'HTTP Task Role '.Str::upper(Str::random(8)),
            'code' => 'http-task-'.Str::lower(Str::random(12)),
            'description' => 'HTTP task test role',
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

    private function createProject(User $manager): Project
    {
        $project = Project::query()->create([
            'name' => 'HTTP Task Project',
            'code' => 'HTTP-TASK-'.Str::upper(Str::random(8)),
            'status' => 'active',
            'manager_id' => $manager->id,
            'created_by' => $manager->id,
            'is_active' => true,
        ]);

        $this->addMember($project, $manager);

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

    private function createTask(Project $project, User $creator): Task
    {
        return Task::query()->create([
            'project_id' => $project->id,
            'title' => 'HTTP Task',
            'description' => 'HTTP task test.',
            'status' => 'todo',
            'priority' => 'normal',
            'assignee_id' => $creator->id,
            'created_by' => $creator->id,
            'is_active' => true,
        ]);
    }
}
