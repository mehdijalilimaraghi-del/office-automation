<?php

namespace Tests\Feature\Core\Departments;

use App\Core\Departments\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Core\Concerns\InteractsWithPermissions;
use Tests\TestCase;

class DepartmentControllerTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithPermissions;

    private function user(): User
    {
        return User::factory()->create();
    }

    private function grant(
        User $user,
        string $permission
    ): void {
        $this->grantPermissions($user, [$permission]);
    }

    public function test_authenticated_user_can_view_departments(): void
    {
        $user = $this->user();

        $this->grant($user, 'departments.view');

        $response = $this->actingAs($user)
            ->get(route('departments.index'));

        $response->assertOk();
    }

    public function test_authenticated_user_can_create_department(): void
    {
        $user = $this->user();

        $this->grant($user, 'departments.create');

        $response = $this->actingAs($user)->post(
            route('departments.store'),
            [
                'name' => 'فناوری اطلاعات',
                'code' => 'IT',
                'description' => 'واحد فناوری اطلاعات',
                'parent_id' => null,
                'is_active' => true,
            ]
        );

        $response->assertRedirect();

        $this->assertDatabaseHas('departments', [
            'name' => 'فناوری اطلاعات',
            'code' => 'IT',
        ]);
    }

    public function test_authenticated_user_can_update_department(): void
    {
        $user = $this->user();

        $this->grant($user, 'departments.edit');

        $department = Department::create([
            'name' => 'فناوری اطلاعات',
            'code' => 'IT',
            'description' => null,
            'parent_id' => null,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->put(
            route('departments.update', $department),
            [
                'name' => 'واحد فناوری اطلاعات',
                'code' => 'ICT',
                'description' => 'توضیحات جدید',
                'parent_id' => null,
                'is_active' => true,
            ]
        );

        $response->assertRedirect();

        $this->assertDatabaseHas('departments', [
            'id' => $department->id,
            'name' => 'واحد فناوری اطلاعات',
            'code' => 'ICT',
        ]);
    }

    public function test_authenticated_user_can_toggle_department_status(): void
    {
        $user = $this->user();

        $this->grant($user, 'departments.toggle_status');

        $department = Department::create([
            'name' => 'فناوری اطلاعات',
            'code' => 'IT',
            'description' => null,
            'parent_id' => null,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->patch(
            route('departments.toggle-status', $department)
        );

        $response->assertRedirect();

        $this->assertDatabaseHas('departments', [
            'id' => $department->id,
            'is_active' => false,
        ]);
    }

    public function test_authenticated_user_can_delete_leaf_department(): void
    {
        $user = $this->user();

        $this->grant($user, 'departments.delete');

        $department = Department::create([
            'name' => 'فناوری اطلاعات',
            'code' => 'IT',
            'description' => null,
            'parent_id' => null,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->delete(
            route('departments.destroy', $department)
        );

        $response->assertRedirect();

        $this->assertDatabaseMissing('departments', [
            'id' => $department->id,
        ]);
    }

    public function test_department_with_children_cannot_be_deleted(): void
    {
        $user = $this->user();

        $this->grant($user, 'departments.delete');

        $parent = Department::create([
            'name' => 'مدیریت',
            'code' => 'MNG',
            'description' => null,
            'parent_id' => null,
            'is_active' => true,
        ]);

        $child = Department::create([
            'name' => 'فناوری اطلاعات',
            'code' => 'IT',
            'description' => null,
            'parent_id' => $parent->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->delete(
            route('departments.destroy', $parent)
        );

        $response->assertSessionHasErrors();

        $this->assertDatabaseHas('departments', [
            'id' => $parent->id,
        ]);

        $this->assertDatabaseHas('departments', [
            'id' => $child->id,
        ]);
    }

    public function test_department_cannot_be_its_own_parent(): void
    {
        $user = $this->user();

        $this->grant($user, 'departments.edit');

        $department = Department::create([
            'name' => 'فناوری اطلاعات',
            'code' => 'IT',
            'description' => null,
            'parent_id' => null,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->put(
            route('departments.update', $department),
            [
                'name' => 'فناوری اطلاعات',
                'code' => 'IT',
                'description' => null,
                'parent_id' => $department->id,
                'is_active' => true,
            ]
        );

        $response->assertSessionHasErrors('parent_id');
    }

    public function test_department_cannot_use_descendant_as_parent(): void
    {
        $user = $this->user();

        $this->grant($user, 'departments.edit');

        $parent = Department::create([
            'name' => 'مدیریت',
            'code' => 'MNG',
            'description' => null,
            'parent_id' => null,
            'is_active' => true,
        ]);

        $child = Department::create([
            'name' => 'فناوری اطلاعات',
            'code' => 'IT',
            'description' => null,
            'parent_id' => $parent->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->put(
            route('departments.update', $parent),
            [
                'name' => 'مدیریت',
                'code' => 'MNG',
                'description' => null,
                'parent_id' => $child->id,
                'is_active' => true,
            ]
        );

        $response->assertSessionHasErrors('parent_id');
    }

    public function test_department_manager_is_added_as_member_when_created(): void
    {
        $user = $this->user();
        $manager = User::factory()->create();

        $this->grant($user, 'departments.create');

        $response = $this->actingAs($user)->post(
            route('departments.store'),
            [
                'name' => 'فناوری اطلاعات',
                'code' => 'IT',
                'description' => null,
                'parent_id' => null,
                'manager_id' => $manager->id,
                'is_active' => true,
            ]
        );

        $response->assertRedirect();

        $department = Department::where('name', 'فناوری اطلاعات')->firstOrFail();

        $this->assertEquals($manager->id, $department->manager_id);

        $this->assertDatabaseHas('department_memberships', [
            'department_id' => $department->id,
            'user_id' => $manager->id,
        ]);
    }

    public function test_department_manager_is_added_as_member_when_updated(): void
    {
        $user = $this->user();
        $manager = User::factory()->create();

        $this->grant($user, 'departments.edit');

        $department = Department::create([
            'name' => 'فناوری اطلاعات',
            'code' => 'IT',
            'description' => null,
            'parent_id' => null,
            'manager_id' => null,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->put(
            route('departments.update', $department),
            [
                'name' => 'فناوری اطلاعات',
                'code' => 'IT',
                'description' => null,
                'parent_id' => null,
                'manager_id' => $manager->id,
                'is_active' => true,
            ]
        );

        $response->assertRedirect();

        $this->assertDatabaseHas('departments', [
            'id' => $department->id,
            'manager_id' => $manager->id,
        ]);

        $this->assertDatabaseHas('department_memberships', [
            'department_id' => $department->id,
            'user_id' => $manager->id,
        ]);
    }

    public function test_department_with_manager_cannot_be_deleted(): void
    {
        $user = $this->user();
        $manager = User::factory()->create();

        $this->grant($user, 'departments.delete');

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
            route('departments.destroy', $department)
        );

        $response->assertSessionHasErrors('department');

        $this->assertDatabaseHas('departments', [
            'id' => $department->id,
        ]);
    }

    public function test_guest_cannot_create_department(): void
    {
        $response = $this->post(
            route('departments.store'),
            [
                'name' => 'فناوری اطلاعات',
                'code' => 'IT',
                'description' => null,
                'parent_id' => null,
                'is_active' => true,
            ]
        );

        $response->assertRedirect(route('login'));
    }

    public function test_guest_cannot_update_department(): void
    {
        $department = Department::create([
            'name' => 'فناوری اطلاعات',
            'code' => 'IT',
            'description' => null,
            'parent_id' => null,
            'is_active' => true,
        ]);

        $response = $this->put(
            route('departments.update', $department),
            [
                'name' => 'واحد جدید',
                'code' => 'NEW',
                'description' => null,
                'parent_id' => null,
                'is_active' => true,
            ]
        );

        $response->assertRedirect(route('login'));
    }

    public function test_guest_cannot_delete_department(): void
    {
        $department = Department::create([
            'name' => 'فناوری اطلاعات',
            'code' => 'IT',
            'description' => null,
            'parent_id' => null,
            'is_active' => true,
        ]);

        $response = $this->delete(
            route('departments.destroy', $department)
        );

        $response->assertRedirect(route('login'));
    }
}