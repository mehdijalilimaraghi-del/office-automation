<?php

namespace Tests\Feature\Core\Users;

use App\Core\Roles\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BootstrapAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_assigns_system_admin_to_existing_active_user(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@example.test',
            'status' => 'active',
        ]);

        $this->artisan('office:bootstrap-admin', [
            'email' => $user->email,
        ])->assertSuccessful();

        $this->assertTrue(
            $user->fresh()->hasPermission('tasks.view')
        );

        $this->assertTrue(
            $user->fresh()->hasRole('SYSTEM_ADMIN')
        );

        $this->assertDatabaseHas('roles', [
            'code' => 'SYSTEM_ADMIN',
            'is_system' => true,
        ]);

        $this->assertSame(
            1,
            Role::query()->where('code', 'SYSTEM_ADMIN')->count()
        );
    }

    public function test_command_rejects_inactive_user(): void
    {
        $user = User::factory()->inactive()->create([
            'email' => 'inactive@example.test',
        ]);

        $this->artisan('office:bootstrap-admin', [
            'email' => $user->email,
        ])->assertFailed();

        $this->assertFalse(
            $user->fresh()->hasRole('SYSTEM_ADMIN')
        );
    }
}
