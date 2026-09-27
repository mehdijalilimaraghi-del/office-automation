<?php

namespace Tests\Feature\Core\Roles;

use App\Core\Permissions\Models\Permission;
use App\Core\Roles\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Core\Concerns\InteractsWithPermissions;
use Tests\TestCase;

class RolePermissionControllerTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithPermissions;

    public function test_authenticated_user_can_view_role_permissions(): void
    {
        $user = User::factory()->create();

        $this->grantPermissions($user, [
            'roles.manage_permissions',
        ]);

        $role = Role::create([
            'name' => 'Manager',
            'code' => 'MANAGER',
            'description' => null,
            'is_active' => true,
            'is_system' => false,
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('roles.permissions.edit', $role));

        $response->assertSuccessful();
    }

    public function test_authenticated_user_can_sync_role_permissions(): void
    {
        $user = User::factory()->create();

        $this->grantPermissions($user, [
            'roles.manage_permissions',
        ]);

        $role = Role::create([
            'name' => 'Manager',
            'code' => 'MANAGER',
            'description' => null,
            'is_active' => true,
            'is_system' => false,
        ]);

        $permission = Permission::create([
            'name' => 'مشاهده کاربران',
            'code' => 'users.view',
            'description' => null,
            'is_active' => true,
            'is_system' => false,
        ]);

        $response = $this
            ->actingAs($user)
            ->put(
                route('roles.permissions.update', $role),
                [
                    'permission_ids' => [
                        $permission->id,
                    ],
                ]
            );

        $response
            ->assertRedirect()
            ->assertSessionHas(
                'success',
                'مجوزهای نقش با موفقیت به‌روزرسانی شد.'
            );

        $this->assertTrue(
            $role->fresh()
                ->permissions()
                ->whereKey($permission->id)
                ->exists()
        );
    }

    public function test_authenticated_user_can_replace_role_permissions(): void
    {
        $user = User::factory()->create();

        $this->grantPermissions($user, [
            'roles.manage_permissions',
        ]);

        $role = Role::create([
            'name' => 'Manager',
            'code' => 'MANAGER',
            'description' => null,
            'is_active' => true,
            'is_system' => false,
        ]);

        $firstPermission = Permission::create([
            'name' => 'مشاهده کاربران',
            'code' => 'users.view',
            'description' => null,
            'is_active' => true,
            'is_system' => false,
        ]);

        $secondPermission = Permission::create([
            'name' => 'ایجاد کاربر',
            'code' => 'users.create',
            'description' => null,
            'is_active' => true,
            'is_system' => false,
        ]);

        $role->permissions()->attach($firstPermission->id);

        $response = $this
            ->actingAs($user)
            ->put(
                route('roles.permissions.update', $role),
                [
                    'permission_ids' => [
                        $secondPermission->id,
                    ],
                ]
            );

        $response->assertRedirect();

        $role = $role->fresh();

        $this->assertFalse(
            $role->permissions()
                ->whereKey($firstPermission->id)
                ->exists()
        );

        $this->assertTrue(
            $role->permissions()
                ->whereKey($secondPermission->id)
                ->exists()
        );
    }

    public function test_authenticated_user_can_remove_all_role_permissions(): void
    {
        $user = User::factory()->create();

        $this->grantPermissions($user, [
            'roles.manage_permissions',
        ]);

        $role = Role::create([
            'name' => 'Manager',
            'code' => 'MANAGER',
            'description' => null,
            'is_active' => true,
            'is_system' => false,
        ]);

        $permission = Permission::create([
            'name' => 'مشاهده کاربران',
            'code' => 'users.view',
            'description' => null,
            'is_active' => true,
            'is_system' => false,
        ]);

        $role->permissions()->attach($permission->id);

        $response = $this
            ->actingAs($user)
            ->put(
                route('roles.permissions.update', $role),
                [
                    'permission_ids' => [],
                ]
            );

        $response->assertRedirect();

        $this->assertCount(
            0,
            $role->fresh()->permissions
        );
    }

    public function test_system_role_permissions_cannot_be_changed(): void
    {
        $user = User::factory()->create();

        $this->grantPermissions($user, [
            'roles.manage_permissions',
        ]);

        $role = Role::create([
            'name' => 'System Administrator',
            'code' => 'SYSTEM_ADMIN',
            'description' => null,
            'is_active' => true,
            'is_system' => true,
        ]);

        $permission = Permission::create([
            'name' => 'مشاهده کاربران',
            'code' => 'users.view',
            'description' => null,
            'is_active' => true,
            'is_system' => false,
        ]);

        $response = $this
            ->actingAs($user)
            ->put(
                route('roles.permissions.update', $role),
                [
                    'permission_ids' => [
                        $permission->id,
                    ],
                ]
            );

        $response->assertForbidden();

        $this->assertCount(
            0,
            $role->fresh()->permissions
        );
    }

    public function test_guest_cannot_update_role_permissions(): void
    {
        $role = Role::create([
            'name' => 'Manager',
            'code' => 'MANAGER',
            'description' => null,
            'is_active' => true,
            'is_system' => false,
        ]);

        $response = $this->put(
            route('roles.permissions.update', $role),
            [
                'permission_ids' => [],
            ]
        );

        $response->assertRedirect(route('login'));
    }
}