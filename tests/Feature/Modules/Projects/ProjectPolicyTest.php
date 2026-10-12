<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Projects;

use App\Core\Departments\Models\Department;
use App\Core\Permissions\Models\Permission;
use App\Core\Roles\Models\Role;
use App\Models\User;
use App\Modules\Projects\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProjectPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_with_view_permission_can_view_project(): void
    {
        $user = $this->userWithPermission('projects.view');

        $project = $this->createProject();

        $this->assertTrue(
            $user->can('view', $project)
        );
    }

    public function test_user_without_view_permission_cannot_view_project(): void
    {
        $user = User::factory()->create();

        $project = $this->createProject();

        $this->assertFalse(
            $user->can('view', $project)
        );
    }

    public function test_only_project_manager_can_update_project(): void
    {
        $manager = $this->userWithPermission('projects.update');
        $member = $this->userWithPermission('projects.update');

        $project = $this->createProject($manager);

        $this->assertTrue(
            $manager->can('update', $project)
        );

        $this->assertFalse(
            $member->can('update', $project)
        );
    }

    public function test_only_project_manager_can_manage_roles(): void
    {
        $manager = $this->userWithPermission('projects.manage_roles');
        $member = $this->userWithPermission('projects.manage_roles');

        $project = $this->createProject($manager);

        $this->assertTrue(
            $manager->can('manageRoles', $project)
        );

        $this->assertFalse(
            $member->can('manageRoles', $project)
        );
    }

    public function test_only_project_manager_can_manage_members(): void
    {
        $manager = $this->userWithPermission('projects.manage_members');
        $member = $this->userWithPermission('projects.manage_members');

        $project = $this->createProject($manager);

        $this->assertTrue(
            $manager->can('manageMembers', $project)
        );

        $this->assertFalse(
            $member->can('manageMembers', $project)
        );
    }

    public function test_only_project_manager_can_delete_project(): void
    {
        $manager = $this->userWithPermission('projects.delete');
        $member = $this->userWithPermission('projects.delete');

        $project = $this->createProject($manager);

        $this->assertTrue(
            $manager->can('delete', $project)
        );

        $this->assertFalse(
            $member->can('delete', $project)
        );
    }

    public function test_project_management_department_manager_can_view_and_delete_project(): void
    {
        $manager = User::factory()->create(['status' => 'active']);
        $project = $this->createProject();

        $parent = Department::query()->firstOrCreate(
            ['name' => 'واحد مدیریت'],
            ['code' => 'MGMT', 'is_active' => true],
        );

        $department = Department::query()->firstOrCreate(
            ['name' => 'مدیریت پروژه', 'parent_id' => $parent->id],
            ['code' => 'PROJECT-MGMT', 'is_active' => true],
        );
        $department->update(['manager_id' => $manager->id]);

        $this->assertTrue($manager->hasPermission('projects.view'));
        $this->assertTrue($manager->hasPermission('projects.delete'));
        $this->assertTrue($manager->can('view', $project));
        $this->assertTrue($manager->can('delete', $project));
    }

    public function test_system_default_project_cannot_be_updated_deleted_or_managed(): void
    {
        $user = $this->userWithPermission('projects.update');
        $role = $user->roles()->firstOrFail();
        $role->permissions()->attach(
            Permission::query()->whereIn('code', [
                'projects.delete',
                'projects.manage_roles',
                'projects.manage_members',
            ])->pluck('id')->all()
        );

        $project = $this->createProject($user);
        $project->update(['is_system_default' => true]);

        $this->assertFalse($user->can('update', $project));
        $this->assertFalse($user->can('delete', $project));
        $this->assertFalse($user->can('manageRoles', $project));
        $this->assertFalse($user->can('manageMembers', $project));
    }

    public function test_inactive_user_cannot_access_project_even_with_permission(): void
    {
        $user = $this->userWithPermission('projects.view');

        $user->update([
            'status' => 'inactive',
        ]);

        $project = $this->createProject();

        $this->assertFalse(
            $user->can('view', $project)
        );
    }

    private function userWithPermission(string $permissionCode): User
    {
        $permission = Permission::query()
            ->where('code', $permissionCode)
            ->firstOrFail();

        $role = Role::query()->create([
            'name' => 'Test Role ' . Str::upper(Str::random(8)),
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

    private function addProjectManagementMembership(User $user): void
    {
        $parent = Department::query()->firstOrCreate(
            ['name' => 'واحد مدیریت'],
            ['code' => 'MGMT', 'is_active' => true],
        );

        $department = Department::query()->firstOrCreate(
            ['name' => 'مدیریت پروژه', 'parent_id' => $parent->id],
            ['code' => 'PROJECT-MGMT', 'is_active' => true],
        );

        $department->members()->syncWithoutDetaching([
            $user->id => ['is_primary' => false, 'joined_at' => now()],
        ]);
    }

    private function createProject(?User $manager = null): Project
    {
        $manager ??= User::factory()->create([
            'status' => 'active',
        ]);

        $this->addProjectManagementMembership($manager);

        return Project::query()->create([
            'name' => 'Policy Test Project',
            'code' => 'POL-' . Str::upper(Str::random(8)),
            'status' => 'active',
            'manager_id' => $manager->id,
            'created_by' => $manager->id,
            'is_active' => true,
        ]);
    }
}