<?php

namespace Tests\Feature\Core\Permissions;

use App\Core\Permissions\Models\Permission;
use App\Core\Roles\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Core\Concerns\InteractsWithPermissions;
use Tests\TestCase;

class PermissionControllerTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithPermissions;

    public function test_authenticated_user_can_view_permissions(): void
    {
        $user = User::factory()->create();

        $this->grantPermissions($user, [
            'permissions.view',
        ]);

        $response = $this->actingAs($user)
            ->get(route('permissions.index'));

        $response->assertOk();
    }

    public function test_authenticated_user_can_create_permission(): void
    {
        $user = User::factory()->create();

        $this->grantPermissions($user, [
            'permissions.create',
        ]);

        $response = $this->actingAs($user)
            ->post(route('permissions.store'), [
                'name' => 'مشاهده گزارش',
                'code' => 'reports.view',
                'description' => 'مشاهده گزارش‌ها',
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('permissions', [
            'name' => 'مشاهده گزارش',
            'code' => 'reports.view',
            'is_active' => true,
            'is_system' => false,
        ]);
    }

    public function test_authenticated_user_can_update_permission(): void
    {
        $user = User::factory()->create();

        $this->grantPermissions($user, [
            'permissions.edit',
        ]);

        $permission = Permission::create([
            'name' => 'مشاهده گزارش',
            'code' => 'reports.view',
            'description' => 'توضیحات اولیه',
            'is_active' => true,
            'is_system' => false,
        ]);

        $response = $this->actingAs($user)
            ->put(route('permissions.update', $permission), [
                'name' => 'مشاهده گزارش‌های جدید',
                'code' => 'reports.view',
                'description' => 'توضیحات جدید',
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('permissions', [
            'id' => $permission->id,
            'name' => 'مشاهده گزارش‌های جدید',
            'code' => 'reports.view',
            'description' => 'توضیحات جدید',
        ]);
    }

    public function test_authenticated_user_can_toggle_permission_status(): void
    {
        $user = User::factory()->create();

        $this->grantPermissions($user, [
            'permissions.toggle_status',
        ]);

        $permission = Permission::create([
            'name' => 'مشاهده گزارش',
            'code' => 'reports.view',
            'description' => null,
            'is_active' => true,
            'is_system' => false,
        ]);

        $response = $this->actingAs($user)
            ->patch(
                route('permissions.toggle-status', $permission)
            );

        $response->assertRedirect();

        $this->assertDatabaseHas('permissions', [
            'id' => $permission->id,
            'is_active' => false,
        ]);
    }

    public function test_authenticated_user_can_delete_permission(): void
    {
        $user = User::factory()->create();

        $this->grantPermissions($user, [
            'permissions.delete',
        ]);

        $permission = Permission::create([
            'name' => 'مشاهده گزارش',
            'code' => 'reports.view',
            'description' => null,
            'is_active' => true,
            'is_system' => false,
        ]);

        $response = $this->actingAs($user)
            ->delete(route('permissions.destroy', $permission));

        $response->assertRedirect();

        $this->assertDatabaseMissing('permissions', [
            'id' => $permission->id,
        ]);
    }

    public function test_system_permission_cannot_be_updated(): void
    {
        $user = User::factory()->create();

        $this->grantPermissions($user, [
            'permissions.edit',
        ]);

        $permission = Permission::create([
            'name' => 'مجوز سیستمی',
            'code' => 'system.permission',
            'description' => null,
            'is_active' => true,
            'is_system' => true,
        ]);

        $response = $this->actingAs($user)
            ->put(route('permissions.update', $permission), [
                'name' => 'تغییر یافته',
                'code' => 'system.permission',
                'description' => null,
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('permissions', [
            'id' => $permission->id,
            'name' => 'مجوز سیستمی',
        ]);
    }

    public function test_system_permission_cannot_be_deleted(): void
    {
        $user = User::factory()->create();

        $this->grantPermissions($user, [
            'permissions.delete',
        ]);

        $permission = Permission::create([
            'name' => 'مجوز سیستمی',
            'code' => 'system.permission',
            'description' => null,
            'is_active' => true,
            'is_system' => true,
        ]);

        $response = $this->actingAs($user)
            ->delete(route('permissions.destroy', $permission));

        $response->assertRedirect();

        $this->assertDatabaseHas('permissions', [
            'id' => $permission->id,
        ]);
    }

    public function test_system_permission_cannot_be_toggled(): void
    {
        $user = User::factory()->create();

        $this->grantPermissions($user, [
            'permissions.toggle_status',
        ]);

        $permission = Permission::create([
            'name' => 'مجوز سیستمی',
            'code' => 'system.permission',
            'description' => null,
            'is_active' => true,
            'is_system' => true,
        ]);

        $response = $this->actingAs($user)
            ->patch(
                route('permissions.toggle-status', $permission)
            );

        $response->assertRedirect();

        $this->assertDatabaseHas('permissions', [
            'id' => $permission->id,
            'is_active' => true,
        ]);
    }

    public function test_guest_cannot_create_permission(): void
    {
        $response = $this->post(route('permissions.store'), [
            'name' => 'مجوز جدید',
            'code' => 'test.permission',
            'description' => null,
        ]);

        $response->assertRedirect(route('login'));
    }

    public function test_guest_cannot_update_permission(): void
    {
        $permission = Permission::create([
            'name' => 'مجوز تست',
            'code' => 'test.permission',
            'description' => null,
            'is_active' => true,
            'is_system' => false,
        ]);

        $response = $this->put(
            route('permissions.update', $permission),
            [
                'name' => 'تغییر یافته',
                'code' => 'test.permission',
                'description' => null,
            ]
        );

        $response->assertRedirect(route('login'));
    }

    public function test_guest_cannot_delete_permission(): void
    {
        $permission = Permission::create([
            'name' => 'مجوز تست',
            'code' => 'test.permission',
            'description' => null,
            'is_active' => true,
            'is_system' => false,
        ]);

        $response = $this->delete(
            route('permissions.destroy', $permission)
        );

        $response->assertRedirect(route('login'));
    }
}