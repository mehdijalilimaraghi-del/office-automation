<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Tasks;

use App\Core\Permissions\Models\Permission;
use App\Core\Roles\Models\Role;
use App\Core\Departments\Models\Department;
use App\Models\User;
use App\Modules\Tasks\Models\Task;
use App\Modules\Tasks\Services\TaskAccessService;
use Database\Seeders\CorePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TaskAccessScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CorePermissionSeeder::class);
    }

    public function test_project_scope_filters_task_list_to_project_membership_and_owned_tasks(): void
    {
        $user = $this->userWithScope('tasks.view', 'project');
        $otherUser = User::factory()->create(['status' => 'active']);

        $project = $this->createProjectFor($user);
        $visible = Task::query()->create([
            'project_id' => $project->id,
            'title' => 'Visible',
            'created_by' => $otherUser->id,
            'status' => 'todo',
            'priority' => 'normal',
        ]);

        $hiddenProject = $this->createProjectFor($otherUser);
        $hidden = Task::query()->create([
            'project_id' => $hiddenProject->id,
            'title' => 'Hidden',
            'created_by' => $otherUser->id,
            'status' => 'todo',
            'priority' => 'normal',
        ]);

        $query = app(TaskAccessService::class)->applyScope(
            Task::query(),
            $user,
            'tasks.view',
        );

        $this->assertTrue($query->whereKey($visible->id)->exists());
        $this->assertFalse($query->whereKey($hidden->id)->exists());
    }

    private function userWithScope(
        string $permissionCode,
        string $scope
    ): User {
        $permission = Permission::query()
            ->where('code', $permissionCode)
            ->firstOrFail();

        $role = Role::query()->create([
            'name' => 'Scope Test Role',
            'code' => 'scope-'.Str::lower(Str::random(10)),
            'description' => null,
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

    private function createProjectFor(User $manager): \App\Modules\Projects\Models\Project
    {
        $project = \App\Modules\Projects\Models\Project::query()->create([
            'name' => 'Scope Test Project',
            'code' => 'SCP-'.Str::upper(Str::random(8)),
            'status' => 'active',
            'manager_id' => $manager->id,
            'created_by' => $manager->id,
            'is_active' => true,
        ]);

        $project->members()->attach($manager->id, [
            'project_role_id' => null,
        ]);

        return $project;
    }
}
