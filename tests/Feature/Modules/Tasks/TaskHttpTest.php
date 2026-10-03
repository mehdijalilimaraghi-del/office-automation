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

    public function test_project_scope_filters_task_index_at_http_layer(): void
    {
        $user = $this->userWithPermissionScope('tasks.view', 'project');
        $visibleProject = $this->createProject($user);
        $visibleTask = $this->createTask($visibleProject, $user);

        $hiddenManager = User::factory()->create(['status' => 'active']);
        $hiddenProject = $this->createProject($hiddenManager);
        $hiddenTask = $this->createTask($hiddenProject, $hiddenManager);

        $response = $this->actingAs($user)
            ->get(route('tasks.index'));

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->component('Tasks/Index')
                ->has('tasks.data', 1)
                ->where('tasks.0.id', $visibleTask->id)
                ->where('tasks.0.title', $visibleTask->title)
        );

        $this->assertNotSame($visibleTask->id, $hiddenTask->id);
    }

    public function test_project_scope_denies_inaccessible_task_show_at_http_layer(): void
    {
        $user = $this->userWithPermissionScope('tasks.view', 'project');
        $hiddenManager = User::factory()->create(['status' => 'active']);
        $hiddenProject = $this->createProject($hiddenManager);
        $task = $this->createTask($hiddenProject, $hiddenManager);

        $this->actingAs($user)
            ->get(route('tasks.show', $task))
            ->assertForbidden();
    }

    public function test_project_scope_denies_inaccessible_task_update_at_http_layer(): void
    {
        $user = $this->userWithPermissionScope('tasks.update', 'project');
        $hiddenManager = User::factory()->create(['status' => 'active']);
        $hiddenProject = $this->createProject($hiddenManager);
        $task = $this->createTask($hiddenProject, $hiddenManager);

        $this->actingAs($user)
            ->put(route('tasks.update', $task), [
                'title' => 'Should Not Update',
                'priority' => 'urgent',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'title' => $task->title,
        ]);
    }

    public function test_project_scope_denies_inaccessible_task_assignment_at_http_layer(): void
    {
        $user = $this->userWithPermissionScope('tasks.assign', 'project');
        $hiddenManager = User::factory()->create(['status' => 'active']);
        $hiddenProject = $this->createProject($hiddenManager);
        $newAssignee = User::factory()->create(['status' => 'active']);
        $this->addMember($hiddenProject, $newAssignee);
        $task = $this->createTask($hiddenProject, $hiddenManager);

        $this->actingAs($user)
            ->patch(route('tasks.assign', $task), [
                'assignee_id' => $newAssignee->id,
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'assignee_id' => $hiddenManager->id,
        ]);
    }

    public function test_project_scope_denies_inaccessible_task_status_change_at_http_layer(): void
    {
        $user = $this->userWithPermissionScope('tasks.change_status', 'project');
        $hiddenManager = User::factory()->create(['status' => 'active']);
        $hiddenProject = $this->createProject($hiddenManager);
        $task = $this->createTask($hiddenProject, $hiddenManager);

        $this->actingAs($user)
            ->patch(route('tasks.change-status', $task), [
                'status' => 'completed',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'status' => 'todo',
        ]);
    }

    public function test_project_scope_denies_inaccessible_task_delete_at_http_layer(): void
    {
        $user = $this->userWithPermissionScope('tasks.delete', 'project');
        $hiddenManager = User::factory()->create(['status' => 'active']);
        $hiddenProject = $this->createProject($hiddenManager);
        $task = $this->createTask($hiddenProject, $hiddenManager);

        $this->actingAs($user)
            ->delete(route('tasks.destroy', $task))
            ->assertForbidden();

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
        ]);
    }

    public function test_task_index_supports_server_side_filters_and_pagination(): void
    {
        $user = $this->userWithPermissionScope('tasks.view', 'project');
        $project = $this->createProject($user);

        foreach (range(1, 16) as $index) {
            $task = $this->createTask($project, $user);
            $task->update([
                'title' => $index === 16 ? 'Find This Task' : 'Other Task '.$index,
                'status' => $index === 16 ? 'completed' : 'todo',
                'priority' => $index === 16 ? 'urgent' : 'normal',
            ]);
        }

        $response = $this->actingAs($user)->get(route('tasks.index', [
            'search' => 'Find This',
            'status' => 'completed',
            'priority' => 'urgent',
            'project_id' => $project->id,
        ]));

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->component('Tasks/Index')
                ->where('tasks.total', 1)
                ->where('tasks.data.0.title', 'Find This Task')
                ->where('filters.search', 'Find This')
                ->where('filters.status', 'completed')
                ->where('filters.priority', 'urgent')
        );

        $allResponse = $this->actingAs($user)->get(route('tasks.index'));
        $allResponse->assertInertia(
            fn ($page) => $page
                ->where('tasks.total', 16)
                ->where('tasks.per_page', 15)
                ->where('tasks.last_page', 2)
        );
    }

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

    private function userWithPermissionScope(string $permissionCode, string $scope): User
    {
        $permission = Permission::query()
            ->where('code', $permissionCode)
            ->firstOrFail();

        $role = Role::query()->create([
            'name' => 'HTTP Task Scope Role '.Str::upper(Str::random(8)),
            'code' => 'http-task-scope-'.Str::lower(Str::random(12)),
            'description' => 'HTTP task scope test role',
            'is_active' => true,
            'is_system' => false,
        ]);

        $role->permissions()->attach($permission->id, [
            'scope' => $scope,
        ]);

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
