<?php

namespace Tests\Feature\Core;

use App\Core\Roles\Models\Role;
use App\Core\Permissions\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoreRoleSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_roles_are_seeded_with_expected_permissions(): void
    {
        $this->seed();

        $rolePermissions = [
            'EMPLOYEE' => [
                'tasks.view',
                'tasks.update',
                'tasks.comment',
            ],
            'PROJECT_MANAGER' => [
                'projects.create',
                'projects.manage_members',
                'tasks.assign',
            ],
            'DEPARTMENT_MANAGER' => [
                'users.manage_departments',
                'departments.manage_positions',
                'tasks.view',
            ],
            'ORGANIZATION_MANAGER' => [
                'users.manage_roles',
                'departments.create',
                'roles.view',
            ],
        ];

        foreach ($rolePermissions as $roleCode => $permissionCodes) {
            $role = Role::query()->where('code', $roleCode)->first();

            $this->assertNotNull($role);
            $this->assertFalse($role->is_system);

            $actualCodes = $role->permissions()
                ->whereIn('permissions.code', $permissionCodes)
                ->pluck('permissions.code')
                ->all();

            foreach ($permissionCodes as $permissionCode) {
                $this->assertContains($permissionCode, $actualCodes, "{$roleCode} is missing {$permissionCode}");
            }
        }
    }

    public function test_rerunning_role_seeder_does_not_overwrite_existing_starter_role_permissions(): void
    {
        $this->seed();

        $role = Role::query()
            ->where('code', 'EMPLOYEE')
            ->firstOrFail();

        $permission = Permission::query()
            ->where('code', 'tasks.view')
            ->firstOrFail();

        $role->permissions()->detach($permission->id);

        $this->seed();

        $this->assertDatabaseMissing('permission_role', [
            'role_id' => $role->id,
            'permission_id' => $permission->id,
        ]);
    }
}
