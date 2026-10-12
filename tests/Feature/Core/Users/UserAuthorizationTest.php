<?php

namespace Tests\Feature\Core\Users;

use App\Core\Permissions\Models\Permission;
use App\Core\Roles\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function permission(
        string $code
    ): Permission {
        return Permission::create([
            'name' => $code,
            'code' => $code,
            'is_active' => true,
            'is_system' => true,
        ]);
    }

    private function role(): Role
    {
        return Role::create([
            'name' => 'مدیر سیستم',
            'code' => 'SYSTEM_ADMIN',
            'is_active' => true,
            'is_system' => false,
        ]);
    }

    public function test_user_with_permission_is_authorized(): void
    {
        $user = User::factory()->create();

        $role = $this->role();

        $permission = $this->permission(
            'users.view'
        );

        $role->permissions()->attach(
            $permission->id
        );

        $user->roles()->attach(
            $role->id
        );

        $this->assertTrue(
            $user->hasPermission('users.view')
        );
    }

    public function test_user_without_permission_is_not_authorized(): void
    {
        $user = User::factory()->create();

        $this->assertFalse(
            $user->hasPermission('users.view')
        );
    }

    public function test_inactive_role_does_not_grant_permission(): void
    {
        $user = User::factory()->create();

        $role = $this->role();

        $role->update([
            'is_active' => false,
        ]);

        $permission = $this->permission(
            'users.view'
        );

        $role->permissions()->attach(
            $permission->id
        );

        $user->roles()->attach(
            $role->id
        );

        $this->assertFalse(
            $user->hasPermission('users.view')
        );
    }

    public function test_inactive_permission_does_not_grant_access(): void
    {
        $user = User::factory()->create();

        $role = $this->role();

        $permission = $this->permission(
            'users.view'
        );

        $permission->update([
            'is_active' => false,
        ]);

        $role->permissions()->attach(
            $permission->id
        );

        $user->roles()->attach(
            $role->id
        );

        $this->assertFalse(
            $user->hasPermission('users.view')
        );
    }

    public function test_inactive_user_has_no_permissions(): void
    {
        $user = User::factory()
            ->inactive()
            ->create();

        $role = $this->role();

        $permission = $this->permission(
            'users.view'
        );

        $role->permissions()->attach(
            $permission->id
        );

        $user->roles()->attach(
            $role->id
        );

        $this->assertFalse(
            $user->hasPermission('users.view')
        );
    }

    public function test_has_any_permission_returns_true_when_one_exists(): void
    {
        $user = User::factory()->create();

        $role = $this->role();

        $permission = $this->permission(
            'users.view'
        );

        $role->permissions()->attach(
            $permission->id
        );

        $user->roles()->attach(
            $role->id
        );

        $this->assertTrue(
            $user->hasAnyPermission([
                'users.view',
                'users.delete',
            ])
        );
    }

    public function test_has_any_permission_returns_false_when_none_exist(): void
    {
        $user = User::factory()->create();

        $this->assertFalse(
            $user->hasAnyPermission([
                'users.view',
                'users.delete',
            ])
        );
    }

    public function test_has_all_permissions_requires_all_permissions(): void
    {
        $user = User::factory()->create();

        $role = $this->role();

        $viewPermission = $this->permission(
            'users.view'
        );

        $editPermission = $this->permission(
            'users.edit'
        );

        $role->permissions()->attach([
            $viewPermission->id,
            $editPermission->id,
        ]);

        $user->roles()->attach(
            $role->id
        );

        $this->assertTrue(
            $user->hasAllPermissions([
                'users.view',
                'users.edit',
            ])
        );

        $this->assertFalse(
            $user->hasAllPermissions([
                'users.view',
                'users.edit',
                'users.delete',
            ])
        );
    }
    public function test_active_system_admin_has_every_permission_without_explicit_grants(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $role = Role::create([
            'name' => 'مدیر سیستم',
            'code' => 'SYSTEM_ADMIN',
            'is_active' => true,
            'is_system' => true,
        ]);
        $user->roles()->attach($role->id);

        $this->assertTrue($user->hasPermission('projects.create'));
        $this->assertTrue($user->hasPermission('tasks.review'));
        $this->assertTrue($user->hasPermission('users.delete'));
        $this->assertSame(
            ['organization'],
            $user->permissionScopes('tasks.review')
        );
    }

    public function test_inactive_system_admin_still_has_no_permissions(): void
    {
        $user = User::factory()->inactive()->create();
        $role = Role::create([
            'name' => 'مدیر سیستم',
            'code' => 'SYSTEM_ADMIN',
            'is_active' => true,
            'is_system' => true,
        ]);
        $user->roles()->attach($role->id);

        $this->assertFalse($user->hasPermission('users.view'));
        $this->assertSame([], $user->permissionScopes('tasks.view'));
    }

}