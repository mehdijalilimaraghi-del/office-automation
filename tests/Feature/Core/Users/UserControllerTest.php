<?php

namespace Tests\Feature\Core\Users;

use App\Core\Departments\Models\Department;
use App\Core\Permissions\Models\Permission;
use App\Core\Roles\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Core\Concerns\InteractsWithPermissions;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithPermissions;

    private function department(): Department
    {
        return Department::create([
            'name' => 'Test Department',
            'code' => 'TEST-DEPT',
            'description' => null,
            'is_active' => true,
        ]);
    }

    private function role(): Role
    {
        return Role::create([
            'name' => 'Test Role',
            'code' => 'TEST_ROLE',
            'description' => null,
            'is_active' => true,
            'is_system' => false,
        ]);
    }

    public function test_authenticated_user_can_view_users(): void
    {
        $user = User::factory()->create();

        $this->grantPermissions($user, [
            'users.view',
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('users.index'));

        $response->assertOk();
    }

    public function test_authenticated_user_can_create_user(): void
    {
        $admin = User::factory()->create();

        $this->grantPermissions($admin, [
            'users.create',
        ]);

        $department = $this->department();
        $role = $this->role();

        $response = $this
            ->actingAs($admin)
            ->post(route('users.store'), [
                'name' => 'New User',
                'email' => 'new-user@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'department_ids' => [$department->id],
                'primary_department_id' => $department->id,
                'status' => 'active',
                'role_ids' => [$role->id],
            ]);

        $response
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'name' => 'New User',
            'email' => 'new-user@example.com',
            'status' => 'active',
        ]);

        $createdUser = User::where(
            'email',
            'new-user@example.com'
        )->firstOrFail();

        $this->assertDatabaseHas('department_memberships', [
            'user_id' => $createdUser->id,
            'department_id' => $department->id,
            'is_primary' => true,
        ]);

        $this->assertDatabaseHas('role_user', [
            'user_id' => $createdUser->id,
            'role_id' => $role->id,
        ]);
    }

    public function test_authenticated_user_can_update_user(): void
    {
        $admin = User::factory()->create();

        $this->grantPermissions($admin, [
            'users.edit',
        ]);

        $user = User::factory()->create([
            'name' => 'Old Name',
            'email' => 'old@example.com',
        ]);

        $role = $this->role();

        $response = $this
            ->actingAs($admin)
            ->put(route('users.update', $user), [
                'name' => 'Updated Name',
                'email' => 'updated@example.com',
                'status' => 'active',
                'role_ids' => [$role->id],
            ]);

        $response
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('role_user', [
            'user_id' => $user->id,
            'role_id' => $role->id,
        ]);
    }

   public function test_authenticated_user_can_update_password(): void
{
    $admin = User::factory()->create();

    $this->grantPermissions($admin, [
        'users.edit',
    ]);

    $user = User::factory()->create([
        'password' => 'old-password',
    ]);

    $department = $this->department();

    $response = $this
        ->actingAs($admin)
        ->put(route('users.update', $user), [
            'name' => $user->name,
            'email' => $user->email,
            'department_ids' => [$department->id],
                'primary_department_id' => $department->id,
            'status' => 'active',
            'role_ids' => [],
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

    $response
        ->assertRedirect()
        ->assertSessionHas('success');

    $user->refresh();

    $this->assertTrue(
        password_verify(
            'new-password',
            $user->password
        )
    );
}

    public function test_authenticated_user_can_toggle_user_status(): void
    {
        $admin = User::factory()->create();

        $this->grantPermissions($admin, [
            'users.toggle_status',
        ]);

        $user = User::factory()->create([
            'status' => 'active',
        ]);

        $response = $this
            ->actingAs($admin)
            ->patch(
                route('users.toggle-status', $user)
            );

        $response
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'status' => 'inactive',
        ]);
    }

    public function test_authenticated_user_can_delete_user(): void
    {
        $admin = User::factory()->create();

        $this->grantPermissions($admin, [
            'users.delete',
        ]);

        $user = User::factory()->create();

        $response = $this
            ->actingAs($admin)
            ->delete(
                route('users.destroy', $user)
            );

        $response
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('users', [
            'id' => $user->id,
        ]);
    }

    public function test_user_cannot_delete_themselves(): void
    {
        $user = User::factory()->create();

        $this->grantPermissions($user, [
            'users.delete',
        ]);

        $response = $this
            ->actingAs($user)
            ->delete(
                route('users.destroy', $user)
            );

        $response
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
        ]);
    }

    public function test_user_cannot_toggle_their_own_status(): void
    {
        $user = User::factory()->create([
            'status' => 'active',
        ]);

        $this->grantPermissions($user, [
            'users.toggle_status',
        ]);

        $response = $this
            ->actingAs($user)
            ->patch(
                route('users.toggle-status', $user)
            );

        $response
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'status' => 'active',
        ]);
    }

    public function test_authenticated_user_cannot_create_duplicate_email(): void
    {
        $admin = User::factory()->create();

        $this->grantPermissions($admin, [
            'users.create',
        ]);

        User::factory()->create([
            'email' => 'existing@example.com',
        ]);

        $response = $this
            ->actingAs($admin)
            ->post(route('users.store'), [
                'name' => 'Another User',
                'email' => 'existing@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'status' => 'active',
                'role_ids' => [],
            ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_guest_cannot_view_users(): void
    {
        $response = $this->get(route('users.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_user_who_manages_department_cannot_be_deleted(): void
    {
        $user = User::factory()->create();
        $manager = User::factory()->create();

        $this->grantPermissions($user, [
            'users.delete',
        ]);

        $department = Department::create([
            'name' => 'فناوری اطلاعات',
            'code' => 'IT',
            'description' => null,
            'parent_id' => null,
            'manager_id' => $manager->id,
            'is_active' => true,
        ]);

        $department->members()->attach($manager->id, [
            'is_primary' => false,
            'joined_at' => now(),
        ]);

        $response = $this->actingAs($user)->delete(
            route('users.destroy', $manager)
        );

        $response
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('users', [
            'id' => $manager->id,
        ]);

        $this->assertDatabaseHas('departments', [
            'id' => $department->id,
            'manager_id' => $manager->id,
        ]);
    }

    public function test_user_cannot_remove_department_membership_while_managing_department(): void
    {
        $user = User::factory()->create();
        $manager = User::factory()->create();
        $department = Department::create([
            'name' => 'فناوری اطلاعات',
            'code' => 'IT',
            'description' => null,
            'parent_id' => null,
            'manager_id' => $manager->id,
            'is_active' => true,
        ]);

        $department->members()->attach($manager->id, [
            'is_primary' => false,
            'joined_at' => now(),
        ]);

        $this->grantPermissions($user, [
            'users.manage_departments',
        ]);

        $response = $this->actingAs($user)->put(
            route('users.departments.update', $manager),
            [
                'department_ids' => [],
                'primary_department_id' => null,
            ]
        );

        $response->assertSessionHasErrors('department_ids');

        $this->assertDatabaseHas('department_memberships', [
            'department_id' => $department->id,
            'user_id' => $manager->id,
        ]);
    }

    public function test_guest_cannot_create_user(): void
    {
        $response = $this->post(route('users.store'), [
            'name' => 'Guest User',
            'email' => 'guest@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'department_ids' => [],
                'primary_department_id' => null,
            'status' => 'active',
            'role_ids' => [],
        ]);

        $response->assertRedirect(route('login'));
    }

    public function test_guest_cannot_update_user(): void
    {
        $user = User::factory()->create();

        $response = $this->put(
            route('users.update', $user),
            [
                'name' => 'Updated',
                'email' => 'updated@example.com',
                'department_ids' => [],
                'primary_department_id' => null,
                'status' => 'active',
                'role_ids' => [],
            ]
        );

        $response->assertRedirect(route('login'));
    }

    public function test_guest_cannot_delete_user(): void
    {
        $user = User::factory()->create();

        $response = $this->delete(
            route('users.destroy', $user)
        );

        $response->assertRedirect(route('login'));
    }
}