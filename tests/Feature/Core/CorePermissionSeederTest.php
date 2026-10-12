<?php

namespace Tests\Feature\Core;

use App\Core\Permissions\Models\Permission;
use Database\Seeders\CorePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CorePermissionSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_and_task_permissions_are_seeded(): void
    {
        $this->seed(CorePermissionSeeder::class);

        $expectedCodes = [
            'projects.view',
            'projects.create',
            'projects.update',
            'projects.delete',
            'projects.manage_roles',
            'projects.manage_members',
            'tasks.view',
            'tasks.create',
            'tasks.update',
            'tasks.delete',
            'tasks.assign',
            'tasks.refer',
            'tasks.change_status',
        ];

        $this->assertSame(
            count($expectedCodes),
            Permission::query()
                ->whereIn('code', $expectedCodes)
                ->count()
        );

        foreach ($expectedCodes as $code) {
            $this->assertDatabaseHas('permissions', [
                'code' => $code,
                'is_active' => true,
                'is_system' => true,
            ]);
        }
    }

    public function test_reseeding_permissions_preserves_explicit_operational_role_grants(): void
    {
        $this->seed(CorePermissionSeeder::class);

        $role = \App\Core\Roles\Models\Role::create([
            'name' => 'نقش تکمیلی',
            'code' => 'OPERATIONAL_GRANT_TEST',
            'is_active' => true,
            'is_system' => false,
        ]);

        $permission = Permission::query()->where('code', 'tasks.view')->firstOrFail();
        $role->permissions()->attach($permission->id, ['scope' => 'organization']);

        $this->seed(CorePermissionSeeder::class);

        $this->assertDatabaseHas('permission_role', [
            'role_id' => $role->id,
            'permission_id' => $permission->id,
            'scope' => 'organization',
        ]);
    }
}
