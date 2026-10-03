<?php

namespace Tests\Feature\Core\Roles;

use App\Core\Permissions\Enums\PermissionScope;
use App\Core\Permissions\Models\Permission;
use App\Core\Roles\Models\Role;
use App\Models\User;
use Database\Seeders\CorePermissionSeeder;
use Database\Seeders\CoreRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoreRoleSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_admin_role_is_seeded_with_all_active_permissions(): void
    {
        $this->seed(CorePermissionSeeder::class);
        $this->seed(CoreRoleSeeder::class);

        $role = Role::query()
            ->where('code', 'SYSTEM_ADMIN')
            ->firstOrFail();

        $this->assertTrue($role->is_system);
        $this->assertTrue($role->is_active);

        $this->assertSame(
            Permission::query()->where('is_active', true)->count(),
            $role->permissions()->count()
        );

        $this->assertSame(
            38,
            $role->permissions()->count()
        );

        $this->assertSame(
            38,
            $role->permissions()
                ->wherePivot('scope', PermissionScope::Organization->value)
                ->count()
        );
    }

    public function test_bootstrap_email_assigns_system_admin_role_to_active_user(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@example.test',
            'status' => 'active',
        ]);

        config([
            'app.name' => 'Office Automation',
        ]);

        putenv('OFFICE_AUTOMATION_BOOTSTRAP_ADMIN_EMAIL=admin@example.test');

        try {
            $this->seed(CorePermissionSeeder::class);
            $this->seed(CoreRoleSeeder::class);

            $user->refresh();

            $this->assertTrue(
                $user->hasRole('SYSTEM_ADMIN')
            );

            $this->assertTrue(
                $user->hasPermission('tasks.view')
            );
        } finally {
            putenv('OFFICE_AUTOMATION_BOOTSTRAP_ADMIN_EMAIL');
        }
    }

    public function test_bootstrap_does_not_assign_role_to_inactive_user(): void
    {
        $user = User::factory()->inactive()->create([
            'email' => 'inactive@example.test',
        ]);

        putenv('OFFICE_AUTOMATION_BOOTSTRAP_ADMIN_EMAIL=inactive@example.test');

        try {
            $this->seed(CorePermissionSeeder::class);
            $this->seed(CoreRoleSeeder::class);

            $this->assertFalse(
                $user->fresh()->hasRole('SYSTEM_ADMIN')
            );
        } finally {
            putenv('OFFICE_AUTOMATION_BOOTSTRAP_ADMIN_EMAIL');
        }
    }
}
