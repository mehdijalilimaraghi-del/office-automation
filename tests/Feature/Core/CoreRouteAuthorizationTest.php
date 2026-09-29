<?php

namespace Tests\Feature\Core;

use App\Core\Permissions\Models\Permission;
use App\Core\Roles\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Core\Concerns\InteractsWithPermissions;
use Tests\TestCase;

class CoreRouteAuthorizationTest extends TestCase
{
    use InteractsWithPermissions;
    use RefreshDatabase;

    public function test_user_without_department_permission_gets_forbidden(): void
    {
        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->get(route('departments.index'))
            ->assertForbidden();
    }

    public function test_user_with_department_view_permission_can_access(): void
    {
        $user = User::factory()->create();

        $this->grantPermissions($user, [
            'departments.view',
        ]);

        $this
            ->actingAs($user)
            ->get(route('departments.index'))
            ->assertOk();
    }

    public function test_user_without_project_permission_gets_forbidden(): void
    {
        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->get(route('projects.index'))
            ->assertForbidden();
    }

    public function test_user_with_project_view_permission_can_access(): void
    {
        $user = User::factory()->create();

        $this->grantPermissions($user, [
            'projects.view',
        ]);

        $this
            ->actingAs($user)
            ->get(route('projects.index'))
            ->assertOk();
    }

    public function test_user_without_task_permission_gets_forbidden(): void
    {
        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->get(route('tasks.index'))
            ->assertForbidden();
    }

    public function test_user_with_task_view_permission_can_access(): void
    {
        $user = User::factory()->create();

        $this->grantPermissions($user, [
            'tasks.view',
        ]);

        $this
            ->actingAs($user)
            ->get(route('tasks.index'))
            ->assertOk();
    }

    public function test_user_without_role_permission_gets_forbidden(): void
    {
        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->get(route('roles.index'))
            ->assertForbidden();
    }

    public function test_user_with_role_view_permission_can_access(): void
    {
        $user = User::factory()->create();

        $this->grantPermissions($user, [
            'roles.view',
        ]);

        $this
            ->actingAs($user)
            ->get(route('roles.index'))
            ->assertOk();
    }

    public function test_user_without_permission_management_access_gets_forbidden(): void
    {
        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->get(route('permissions.index'))
            ->assertForbidden();
    }

    public function test_user_with_permission_view_can_access_permission_page(): void
    {
        $user = User::factory()->create();

        $this->grantPermissions($user, [
            'permissions.view',
        ]);

        $this
            ->actingAs($user)
            ->get(route('permissions.index'))
            ->assertOk();
    }

    public function test_role_permission_management_requires_specific_permission(): void
    {
        $user = User::factory()->create();

        $role = Role::create([
            'name' => 'Test Role',
            'code' => 'TEST_ROLE',
            'is_active' => true,
            'is_system' => false,
        ]);

        $this
            ->actingAs($user)
            ->get(route('roles.permissions.edit', $role))
            ->assertForbidden();
    }

    public function test_role_permission_management_is_allowed_with_permission(): void
    {
        $user = User::factory()->create();

        $role = Role::create([
            'name' => 'Test Role',
            'code' => 'TEST_ROLE',
            'is_active' => true,
            'is_system' => false,
        ]);

        $this->grantPermissions($user, [
            'roles.manage_permissions',
        ]);

        $this
            ->actingAs($user)
            ->get(route('roles.permissions.edit', $role))
            ->assertOk();
    }

    public function test_inactive_user_cannot_access_core_route(): void
    {
        $user = User::factory()
            ->inactive()
            ->create();

        $this->grantPermissions($user, [
            'departments.view',
        ]);

        $this
            ->actingAs($user)
            ->get(route('departments.index'))
            ->assertForbidden();
    }

    public function test_inactive_role_cannot_grant_route_access(): void
    {
        $user = User::factory()->create();

        $role = Role::create([
            'name' => 'Inactive Role',
            'code' => 'INACTIVE_ROLE',
            'is_active' => false,
            'is_system' => false,
        ]);

        $permission = Permission::create([
            'name' => 'مشاهده واحدها',
            'code' => 'departments.view',
            'is_active' => true,
            'is_system' => true,
        ]);

        $role->permissions()->attach($permission->id);
        $user->roles()->attach($role->id);

        $this
            ->actingAs($user)
            ->get(route('departments.index'))
            ->assertForbidden();
    }

    public function test_inactive_permission_cannot_grant_route_access(): void
    {
        $user = User::factory()->create();

        $role = Role::create([
            'name' => 'Test Role',
            'code' => 'TEST_ROLE',
            'is_active' => true,
            'is_system' => false,
        ]);

        $permission = Permission::create([
            'name' => 'مشاهده واحدها',
            'code' => 'departments.view',
            'is_active' => false,
            'is_system' => true,
        ]);

        $role->permissions()->attach($permission->id);
        $user->roles()->attach($role->id);

        $this
            ->actingAs($user)
            ->get(route('departments.index'))
            ->assertForbidden();
    }
}