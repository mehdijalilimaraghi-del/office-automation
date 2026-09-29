<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Projects;

use App\Core\Permissions\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectTaskPermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_and_task_permissions_exist(): void
    {
        $expectedPermissions = [
            'projects.view',
            'projects.create',
            'projects.update',
            'projects.delete',
            'projects.manage_roles',
            'projects.manage_members',
            'tasks.view',
            'tasks.create',
            'tasks.update',
            'tasks.assign',
            'tasks.change_status',
            'tasks.delete',
        ];

        foreach ($expectedPermissions as $code) {
            $this->assertDatabaseHas('permissions', [
                'code' => $code,
                'is_active' => true,
                'is_system' => true,
            ]);
        }
    }

    public function test_project_and_task_permission_codes_are_unique(): void
    {
        $codes = [
            'projects.view',
            'projects.create',
            'projects.update',
            'projects.delete',
            'projects.manage_roles',
            'projects.manage_members',
            'tasks.view',
            'tasks.create',
            'tasks.update',
            'tasks.assign',
            'tasks.change_status',
            'tasks.delete',
        ];

        $this->assertSame(
            count($codes),
            count(array_unique($codes))
        );

        $this->assertSame(
            count($codes),
            Permission::query()
                ->whereIn('code', $codes)
                ->count()
        );
    }
}