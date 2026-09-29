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
}
