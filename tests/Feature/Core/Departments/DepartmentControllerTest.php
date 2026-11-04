<?php

namespace Tests\Feature\Core\Departments;

use App\Core\Departments\Models\Department;
use App\Models\User;
use App\Modules\Projects\Models\Project;
use App\Modules\Tasks\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
        ]);
    }

    public function test_creating_department_does_not_create_a_separate_default_project(): void
    {
        $user = $this->user();

        $this->grant($user, 'departments.create');

        $this->actingAs($user)->post(
            route('departments.store'),
            [
                'name' => 'فناوری اطلاعات',
                'code' => 'IT',
                'description' => null,
                'parent_id' => null,
                'is_active' => true,
            ]
        )->assertRedirect();

        $department = Department::where('name', 'فناوری اطلاعات')->firstOrFail();

        $this->assertDatabaseMissing('projects', [
            'department_id' => $department->id,
            'is_system_default' => true,
        ]);

        $this->assertDatabaseHas('project_sections', [
            'department_id' => $department->id,
            'name' => 'فناوری اطلاعات',
        ]);
    }

    public function test_department_manager_is_not_added_to_a_per_department_default_project(): void
    {
        $user = $this->user();
        $manager = User::factory()->create();

        $this->grant($user, 'departments.create');

        $this->actingAs($user)->post(
            route('departments.store'),
            [
                'name' => 'فناوری اطلاعات',
                'code' => 'IT',
                'description' => null,
                'parent_id' => null,
                'manager_id' => $manager->id,
                'is_active' => true,
            ]
        )->assertRedirect();

        $department = Department::where('name', 'فناوری اطلاعات')->firstOrFail();

        $this->assertDatabaseHas('departments', [
            'id' => $department->id,
            'manager_id' => $manager->id,
        ]);

        $this->assertDatabaseMissing('projects', [
            'department_id' => $department->id,
            'is_system_default' => true,
        ]);
    }


    public function test_department_manager_signature_is_stored_as_a_private_unit_asset(): void
    {
        Storage::fake('local');
        $user = $this->user();
        $manager = User::factory()->create(['status' => 'active']);
        $this->grant($user, 'departments.create');

        $this->actingAs($user)->post(route('departments.store'), [
            'name' => 'واحد دارای امضا',
            'code' => 'SIGN-'.strtoupper(bin2hex(random_bytes(2))),
            'manager_id' => $manager->id,
            'manager_signature' => UploadedFile::fake()->image('signature.png', 400, 180),
            'manager_signature_active' => true,
            'is_active' => true,
        ])->assertRedirect();

        $department = Department::query()->where('manager_id', $manager->id)->firstOrFail();

        $this->assertNotEmpty($department->manager_signature_path);
        $this->assertTrue($department->manager_signature_active);
        Storage::disk('local')->assertExists($department->manager_signature_path);
    }

    public function test_changing_department_manager_deactivates_the_previous_manager_signature(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('organization/department-signatures/current.png', 'signature');
        $editor = $this->user();
        $oldManager = User::factory()->create(['status' => 'active']);
        $newManager = User::factory()->create(['status' => 'active']);
        $department = Department::query()->create([
            'name' => 'واحد تغییر مدیر',
            'code' => 'MGR-'.strtoupper(bin2hex(random_bytes(2))),
            'manager_id' => $oldManager->id,
            'manager_signature_path' => 'organization/department-signatures/current.png',
            'manager_signature_active' => true,
            'is_active' => true,
        ]);
        $this->grant($editor, 'departments.edit');

        $this->actingAs($editor)->put(route('departments.update', $department), [
            'name' => $department->name,
            'code' => $department->code,
            'manager_id' => $newManager->id,
            'manager_signature_active' => true,
            'is_active' => true,
        ])->assertRedirect();

        $department->refresh();
        $this->assertSame((int) $newManager->id, (int) $department->manager_id);
        $this->assertFalse($department->manager_signature_active);
        $this->assertTrue($department->members()->whereKey($newManager->id)->exists());
    }

    public function test_authenticated_user_can_create_department_with_icon(): void
    {
        $user = $this->user();

        $this->grant($user, 'departments.create');

        $response = $this->actingAs($user)->post(
            route('departments.store'),
            [
                'name' => 'فناوری اطلاعات',
                'code' => 'IT',
                'description' => 'واحد فناوری اطلاعات',
                'icon' => 'handshake',
                'parent_id' => null,
                'is_active' => true,
            ]
        );

        $response->assertRedirect();

        $this->assertDatabaseHas('departments', [
            'name' => 'فناوری اطلاعات',
            'code' => 'IT',
            'icon' => 'handshake',
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

    public function test_deleting_department_removes_default_project_but_preserves_its_tasks(): void
    {
        $user = $this->user();
        $manager = User::factory()->create();

        $this->grant($user, 'departments.delete');

        $department = Department::create([
            'name' => 'واحد حذف',
            'code' => 'DEL',
            'description' => null,
            'parent_id' => null,
            'manager_id' => null,
            'is_active' => true,
        ]);

        $defaultProject = Project::query()->create([
            'department_id' => $department->id,
            'name' => 'پروژه عمومی واحد حذف',
            'code' => 'DEPT-'.$department->id.'-DEFAULT',
            'status' => 'active',
            'manager_id' => null,
            'created_by' => null,
            'is_active' => true,
            'is_system_default' => true,
        ]);

        $task = Task::query()->create([
            'project_id' => $defaultProject->id,
            'department_id' => $department->id,
            'title' => 'Task survives department deletion',
            'status' => 'todo',
            'priority' => 'normal',
            'assignee_id' => $manager->id,
            'created_by' => $manager->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->delete(
            route('departments.destroy', $department)
        );

        $response->assertRedirect();

        $this->assertDatabaseMissing('departments', [
            'id' => $department->id,
        ]);

        $this->assertDatabaseMissing('projects', [
            'id' => $defaultProject->id,
        ]);

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'project_id' => null,
            'department_id' => null,
        ]);
    }

    public function test_regular_projects_survive_department_deletion(): void
    {
        $user = $this->user();
        $this->grant($user, 'departments.delete');

        $department = Department::create([
            'name' => 'واحد پروژه',
            'code' => 'PRJDEP',
            'description' => null,
            'parent_id' => null,
            'manager_id' => null,
            'is_active' => true,
        ]);

        $project = Project::query()->create([
            'department_id' => $department->id,
            'name' => 'پروژه عادی',
            'code' => 'PRJ-DEP-001',
            'status' => 'active',
            'manager_id' => null,
            'created_by' => null,
            'is_active' => true,
            'is_system_default' => false,
        ]);

        $this->actingAs($user)
            ->delete(route('departments.destroy', $department))
            ->assertRedirect();

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'department_id' => null,
            'is_system_default' => false,
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

    public function test_department_hierarchy_supports_multiple_levels(): void
    {
        $user = $this->user();

        $this->grant($user, 'departments.view');

        $parent = Department::create([
            'name' => 'مدیریت',
            'code' => 'MNG',
            'description' => null,
            'parent_id' => null,
            'is_active' => true,
        ]);

        $child = Department::create([
            'name' => 'اداری',
            'code' => 'ADM',
            'description' => null,
            'parent_id' => $parent->id,
            'is_active' => true,
        ]);

        $grandchild = Department::create([
            'name' => 'دبیرخانه',
            'code' => 'SEC',
            'description' => null,
            'parent_id' => $child->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)
            ->get(route('departments.index'));

        $response->assertOk();

        $parent->refresh();
        $child->refresh();
        $grandchild->refresh();

        $withCounts = Department::query()
            ->withCount('children')
            ->whereKey([$parent->id, $child->id, $grandchild->id])
            ->get()
            ->keyBy('id');

        $this->assertNull($parent->parent_id);
        $this->assertSame($parent->id, (int) $child->parent_id);
        $this->assertSame($child->id, (int) $grandchild->parent_id);
        $this->assertSame(1, $parent->children()->count());
        $this->assertSame(1, $child->children()->count());
        $this->assertSame(0, $grandchild->children()->count());
        $this->assertSame(1, (int) $withCounts[$parent->id]->children_count);
        $this->assertSame(1, (int) $withCounts[$child->id]->children_count);
        $this->assertSame(0, (int) $withCounts[$grandchild->id]->children_count);
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

    public function test_changing_department_manager_does_not_change_legacy_project_manager(): void
    {
        $user = $this->user();
        $oldManager = User::factory()->create();
        $newManager = User::factory()->create();

        $this->grant($user, 'departments.edit');

        $department = Department::create([
            'name' => 'فناوری اطلاعات',
            'code' => 'IT',
            'description' => null,
            'parent_id' => null,
            'manager_id' => $oldManager->id,
            'is_active' => true,
        ]);

        $department->members()->attach($oldManager->id, [
            'is_primary' => false,
            'joined_at' => now(),
        ]);

        $project = Project::query()->create([
            'department_id' => $department->id,
            'name' => 'پروژه عمومی فناوری اطلاعات',
            'code' => 'DEPT-'.$department->id.'-DEFAULT',
            'status' => 'active',
            'manager_id' => $oldManager->id,
            'created_by' => null,
            'is_active' => true,
            'is_system_default' => true,
        ]);

        $this->actingAs($user)->put(
            route('departments.update', $department),
            [
                'name' => 'فناوری اطلاعات',
                'code' => 'IT',
                'description' => null,
                'parent_id' => null,
                'manager_id' => $newManager->id,
                'is_active' => true,
            ]
        )->assertRedirect();

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'manager_id' => $oldManager->id,
        ]);

        $this->assertDatabaseMissing('project_user', [
            'project_id' => $project->id,
            'user_id' => $newManager->id,
        ]);
    }

    public function test_removing_department_manager_does_not_change_legacy_project_manager(): void
    {
        $user = $this->user();
        $manager = User::factory()->create();

        $this->grant($user, 'departments.edit');

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

        $project = Project::query()->create([
            'department_id' => $department->id,
            'name' => 'پروژه عمومی فناوری اطلاعات',
            'code' => 'DEPT-'.$department->id.'-DEFAULT',
            'status' => 'active',
            'manager_id' => $manager->id,
            'created_by' => null,
            'is_active' => true,
            'is_system_default' => true,
        ]);

        $this->actingAs($user)->put(
            route('departments.update', $department),
            [
                'name' => 'فناوری اطلاعات',
                'code' => 'IT',
                'description' => null,
                'parent_id' => null,
                'manager_id' => null,
                'is_active' => true,
            ]
        )->assertRedirect();

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'manager_id' => $manager->id,
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