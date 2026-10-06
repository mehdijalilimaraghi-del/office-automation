<?php

namespace Tests\Feature\Core\Roles;

use App\Core\Permissions\Enums\PermissionScope;
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
                    'permissions' => [
                        [
                            'permission_id' => $permission->id,
                            'scope' => PermissionScope::Organization->value,
                        ],
                    ],
                ]
            );

        $response
            ->assertRedirect()
            ->assertSessionHas(
                'success',
                'مجوزهای نقش با موفقیت به‌روزرسانی شد.'
            );

        $assignedPermission = $role->fresh()
            ->permissions()
            ->whereKey($permission->id)
            ->first();

        $this->assertNotNull($assignedPermission);
        $this->assertSame(
            PermissionScope::Organization->value,
            $assignedPermission->pivot->scope
        );
    }

    public function test_non_scope_aware_permission_rejects_non_organization_scope(): void
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

        $this->actingAs($user)
            ->put(
                route('roles.permissions.update', $role),
                [
                    'permissions' => [
                        [
                            'permission_id' => $permission->id,
                            'scope' => PermissionScope::Department->value,
                        ],
                    ],
                ]
            )
            ->assertSessionHasErrors('permissions.0.scope');

        $this->assertDatabaseMissing('permission_role', [
            'role_id' => $role->id,
            'permission_id' => $permission->id,
        ]);
    }

    public function test_project_permission_accepts_a_project_scope(): void
    {
        $user = User::factory()->create();

        $this->grantPermissions($user, [
            'roles.manage_permissions',
        ]);

        $role = Role::create([
            'name' => 'Project Manager',
            'code' => 'PROJECT_MANAGER',
            'description' => null,
            'is_active' => true,
            'is_system' => false,
        ]);

        $permission = Permission::create([
            'name' => 'مشاهده پروژه‌های Scope',
            'code' => 'projects.view',
            'description' => null,
            'is_active' => true,
            'is_system' => false,
        ]);

        $this->actingAs($user)
            ->put(
                route('roles.permissions.update', $role),
                [
                    'permissions' => [
                        [
                            'permission_id' => $permission->id,
                            'scope' => PermissionScope::Project->value,
                        ],
                    ],
                ]
            )
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(
            PermissionScope::Project->value,
            $role->fresh()->permissions()->firstOrFail()->pivot->scope
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
                    'permissions' => [
                        [
                            'permission_id' => $secondPermission->id,
                            'scope' => PermissionScope::Organization->value,
                        ],
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
                    'permissions' => [],
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
                    'permissions' => [
                        [
                            'permission_id' => $permission->id,
                            'scope' => PermissionScope::Organization->value,
                        ],
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
                'permissions' => [],
            ]
        );

        $response->assertRedirect(route('login'));
    }
}