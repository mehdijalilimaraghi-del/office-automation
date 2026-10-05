<?php

namespace Tests\Feature\Core;

use App\Core\Permissions\Models\Permission;
use App\Core\Roles\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SystemAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_admin_command_creates_standalone_full_access_admin(): void
    {
        $this->artisan('office:create-system-admin', [
            'username' => 'systemadmin',
            '--email' => 'systemadmin@system.local',
            '--password' => 'Admin@2026#System',
        ])->assertExitCode(0);

        $user = User::query()
            ->where('username', 'systemadmin')
            ->firstOrFail();

        $role = Role::query()
            ->where('code', 'SYSTEM_ADMIN')
            ->firstOrFail();

        $this->assertSame('active', $user->status);
        $this->assertTrue(Hash::check('Admin@2026#System', $user->password));
        $this->assertTrue($user->hasRole('SYSTEM_ADMIN'));
        $this->assertSame(Permission::query()->where('is_active', true)->count(), $role->permissions()->count());
        $this->assertDatabaseCount('department_memberships', 0);
        $this->assertDatabaseCount('position_assignments', 0);
        $this->assertDatabaseCount('project_user', 0);
    }
}
