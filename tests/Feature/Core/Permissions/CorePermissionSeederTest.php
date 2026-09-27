<?php

namespace Tests\Feature\Core\Permissions;

use App\Core\Permissions\Models\Permission;
use Database\Seeders\CorePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CorePermissionSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_core_permissions_are_seeded(): void
    {
        $this->seed(
            CorePermissionSeeder::class
        );

        $this->assertDatabaseHas(
            'permissions',
            [
                'code' => 'users.view',
                'is_system' => true,
            ]
        );

        $this->assertDatabaseHas(
            'permissions',
            [
                'code' => 'users.create',
                'is_system' => true,
            ]
        );

        $this->assertDatabaseHas(
            'permissions',
            [
                'code' => 'roles.manage_permissions',
                'is_system' => true,
            ]
        );

        $this->assertSame(
            22,
            Permission::count()
        );
    }
}