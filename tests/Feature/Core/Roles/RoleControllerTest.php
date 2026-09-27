<?php

namespace Tests\Feature\Core\Roles;

use App\Core\Roles\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Core\Concerns\InteractsWithPermissions;
use Tests\TestCase;

class RoleControllerTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithPermissions;

    public function test_authenticated_user_can_view_roles(): void
    {
        $user = User::factory()->create();

        $this->grantPermissions($user, [
            'roles.view',
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('roles.index'));

        $response->assertOk();
    }

    public function test_authenticated_user_can_create_role(): void
    {
        $user = User::factory()->create();

        $this->grantPermissions($user, [
            'roles.create',
        ]);

        $response = $this
            ->actingAs($user)
            ->post(route('roles.store'), [
                'name' => 'مدیر فروش',
                'code' => 'SALES_MANAGER',
                'description' => 'نقش مدیر فروش',
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('roles', [
            'name' => 'مدیر فروش',
            'code' => 'SALES_MANAGER',
            'is_active' => true,
            'is_system' => false,
        ]);
    }

    public function test_authenticated_user_can_update_role(): void
    {
        $user = User::factory()->create();

        $this->grantPermissions($user, [
            'roles.edit',
        ]);

        $role = Role::create([
            'name' => 'نقش قدیمی',
            'code' => 'OLD_ROLE',
            'description' => 'توضیحات قدیمی',
            'is_active' => true,
            'is_system' => false,
        ]);

        $response = $this
            ->actingAs($user)
            ->put(route('roles.update', $role), [
                'name' => 'نقش جدید',
                'code' => 'NEW_ROLE',
                'description' => 'توضیحات جدید',
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('roles', [
            'id' => $role->id,
            'name' => 'نقش جدید',
            'code' => 'NEW_ROLE',
            'description' => 'توضیحات جدید',
        ]);
    }

    public function test_authenticated_user_can_toggle_role_status(): void
    {
        $user = User::factory()->create();

        $this->grantPermissions($user, [
            'roles.toggle_status',
        ]);

        $role = Role::create([
            'name' => 'نقش تست',
            'code' => 'TEST_ROLE',
            'description' => null,
            'is_active' => true,
            'is_system' => false,
        ]);

        $response = $this
            ->actingAs($user)
            ->patch(route('roles.toggle-status', $role));

        $response->assertRedirect();

        $this->assertDatabaseHas('roles', [
            'id' => $role->id,
            'is_active' => false,
        ]);
    }

    public function test_authenticated_user_can_delete_role(): void
    {
        $user = User::factory()->create();

        $this->grantPermissions($user, [
            'roles.delete',
        ]);

        $role = Role::create([
            'name' => 'نقش قابل حذف',
            'code' => 'DELETE_ROLE',
            'description' => null,
            'is_active' => true,
            'is_system' => false,
        ]);

        $response = $this
            ->actingAs($user)
            ->delete(route('roles.destroy', $role));

        $response->assertRedirect();

        $this->assertDatabaseMissing('roles', [
            'id' => $role->id,
        ]);
    }

    public function test_system_role_cannot_be_updated(): void
    {
        $user = User::factory()->create();

        $this->grantPermissions($user, [
            'roles.edit',
        ]);

        $role = Role::create([
            'name' => 'نقش سیستمی',
            'code' => 'SYSTEM_ROLE',
            'description' => null,
            'is_active' => true,
            'is_system' => true,
        ]);

        $response = $this
            ->actingAs($user)
            ->put(route('roles.update', $role), [
                'name' => 'تغییر یافته',
                'code' => 'CHANGED_ROLE',
                'description' => null,
            ]);

        $response->assertForbidden();

        $this->assertDatabaseHas('roles', [
            'id' => $role->id,
            'name' => 'نقش سیستمی',
            'code' => 'SYSTEM_ROLE',
        ]);
    }

    public function test_system_role_cannot_be_deleted(): void
    {
        $user = User::factory()->create();

        $this->grantPermissions($user, [
            'roles.delete',
        ]);

        $role = Role::create([
            'name' => 'نقش سیستمی',
            'code' => 'SYSTEM_ROLE',
            'description' => null,
            'is_active' => true,
            'is_system' => true,
        ]);

        $response = $this
            ->actingAs($user)
            ->delete(route('roles.destroy', $role));

        $response->assertForbidden();

        $this->assertDatabaseHas('roles', [
            'id' => $role->id,
        ]);
    }

    public function test_system_role_cannot_be_toggled(): void
    {
        $user = User::factory()->create();

        $this->grantPermissions($user, [
            'roles.toggle_status',
        ]);

        $role = Role::create([
            'name' => 'نقش سیستمی',
            'code' => 'SYSTEM_ROLE',
            'description' => null,
            'is_active' => true,
            'is_system' => true,
        ]);

        $response = $this
            ->actingAs($user)
            ->patch(route('roles.toggle-status', $role));

        $response->assertForbidden();

        $this->assertDatabaseHas('roles', [
            'id' => $role->id,
            'is_active' => true,
        ]);
    }

    public function test_guest_cannot_create_role(): void
    {
        $response = $this->post(route('roles.store'), [
            'name' => 'نقش تست',
            'code' => 'TEST_ROLE',
            'description' => null,
        ]);

        $response->assertRedirect(route('login'));
    }

    public function test_guest_cannot_update_role(): void
    {
        $role = Role::create([
            'name' => 'نقش تست',
            'code' => 'TEST_ROLE',
            'description' => null,
            'is_active' => true,
            'is_system' => false,
        ]);

        $response = $this->put(route('roles.update', $role), [
            'name' => 'نقش جدید',
            'code' => 'NEW_ROLE',
            'description' => null,
        ]);

        $response->assertRedirect(route('login'));
    }

    public function test_guest_cannot_delete_role(): void
    {
        $role = Role::create([
            'name' => 'نقش تست',
            'code' => 'TEST_ROLE',
            'description' => null,
            'is_active' => true,
            'is_system' => false,
        ]);

        $response = $this->delete(route('roles.destroy', $role));

        $response->assertRedirect(route('login'));
    }
}