<?php

namespace Tests\Feature\Core;

use App\Core\Permissions\Models\Permission;
use App\Core\Roles\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoreRoleSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_system_admin_is_seeded_and_receives_all_active_permissions(): void
    {
        $this->seed();

        $systemAdmin = Role::query()
            ->where('code', 'SYSTEM_ADMIN')
            ->firstOrFail();

        $this->assertTrue($systemAdmin->is_system);
        $this->assertTrue($systemAdmin->is_active);

        $activePermissionCount = Permission::query()
            ->where('is_active', true)
            ->count();

        $this->assertSame(
            $activePermissionCount,
            $systemAdmin->permissions()->count()
        );

        $this->assertSame(
            1,
            Role::query()->count()
        );

        $this->assertDatabaseMissing('roles', ['code' => 'EMPLOYEE']);
        $this->assertDatabaseMissing('roles', ['code' => 'PROJECT_MANAGER']);
        $this->assertDatabaseMissing('roles', ['code' => 'DEPARTMENT_SUPERVISOR']);
        $this->assertDatabaseMissing('roles', ['code' => 'ORGANIZATION_MANAGER']);
    }

    public function test_rerunning_role_seeder_keeps_system_admin_permissions_in_sync(): void
    {
        $this->seed();

        $role = Role::query()
            ->where('code', 'SYSTEM_ADMIN')
            ->firstOrFail();

        $permission = Permission::query()
            ->where('code', 'tasks.view')
            ->firstOrFail();

        $role->permissions()->detach($permission->id);

        $this->seed();

        $this->assertDatabaseHas('permission_role', [
            'role_id' => $role->id,
            'permission_id' => $permission->id,
        ]);
    }
}
