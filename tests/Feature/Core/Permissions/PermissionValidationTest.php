<?php

namespace Tests\Feature\Core\Permissions;

use App\Core\Permissions\Models\Permission;
use App\Core\Roles\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionValidationTest extends TestCase
{
    use RefreshDatabase;

    private function grantCreatePermission(User $user): void
    {
        $role = Role::create([
            'name' => 'Test Permission Manager',
            'code' => 'TEST_PERMISSION_MANAGER',
            'description' => null,
            'is_active' => true,
            'is_system' => false,
        ]);

        $permission = Permission::create([
            'name' => 'ایجاد مجوز',
            'code' => 'permissions.create',
            'description' => null,
            'is_active' => true,
            'is_system' => true,
        ]);

        $role->permissions()->attach($permission->id);

        $user->roles()->attach($role->id);
    }

    public function test_permission_code_must_be_valid(): void
    {
        $user = User::factory()->create();

        $this->grantCreatePermission($user);

        $response = $this
            ->actingAs($user)
            ->post(route('permissions.store'), [
                'name' => 'مجوز تست',
                'code' => 'invalid code',
                'description' => null,
            ]);

        $response->assertSessionHasErrors('code');
    }

    public function test_permission_code_must_start_with_a_letter(): void
    {
        $user = User::factory()->create();

        $this->grantCreatePermission($user);

        $response = $this
            ->actingAs($user)
            ->post(route('permissions.store'), [
                'name' => 'مجوز تست',
                'code' => '123.permission',
                'description' => null,
            ]);

        $response->assertSessionHasErrors('code');
    }

    public function test_permission_code_accepts_supported_characters(): void
    {
        $user = User::factory()->create();

        $this->grantCreatePermission($user);

        $response = $this
            ->actingAs($user)
            ->post(route('permissions.store'), [
                'name' => 'مجوز تست',
                'code' => 'projects.tasks:view',
                'description' => null,
            ]);

        $response->assertSessionDoesntHaveErrors('code');

        $this->assertDatabaseHas('permissions', [
            'code' => 'projects.tasks:view',
        ]);
    }
}