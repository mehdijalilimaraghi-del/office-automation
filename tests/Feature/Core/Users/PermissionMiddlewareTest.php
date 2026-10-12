<?php

namespace Tests\Feature\Core\Users;

use App\Core\Permissions\Models\Permission;
use App\Core\Roles\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PermissionMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware([
            'web',
            'auth',
            'permission:users.view',
        ])->get(
            '/test-permission-route',
            fn () => response()->json([
                'ok' => true,
            ])
        );
    }

    public function test_guest_is_rejected(): void
    {
        $this
            ->get('/test-permission-route')
            ->assertRedirect(route('login'));
    }

    public function test_user_without_permission_gets_forbidden(): void
    {
        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->get('/test-permission-route')
            ->assertForbidden();
    }

    public function test_user_with_permission_can_access_route(): void
    {
        $user = User::factory()->create();

        $role = Role::create([
            'name' => 'مدیر',
            'code' => 'ADMIN',
            'is_active' => true,
            'is_system' => false,
        ]);

        $permission = Permission::create([
            'name' => 'مشاهده کاربران',
            'code' => 'users.view',
            'is_active' => true,
            'is_system' => true,
        ]);

        $role->permissions()->attach(
            $permission->id
        );

        $user->roles()->attach(
            $role->id
        );

        $this
            ->actingAs($user)
            ->get('/test-permission-route')
            ->assertOk()
            ->assertJson([
                'ok' => true,
            ]);
    }
    public function test_system_admin_can_access_permission_route_without_explicit_route_permission(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $role = Role::create([
            'name' => 'مدیر سیستم',
            'code' => 'SYSTEM_ADMIN',
            'is_active' => true,
            'is_system' => true,
        ]);
        $user->roles()->attach($role->id);

        $this->actingAs($user)
            ->get('/test-permission-route')
            ->assertOk()
            ->assertJson(['ok' => true]);
    }

}