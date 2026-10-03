<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Projects;

use App\Core\Permissions\Models\Permission;
use App\Core\Roles\Models\Role;
use App\Models\User;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProjectHttpTest extends TestCase
{
    use RefreshDatabase;


    public function test_authorized_user_can_open_paginated_project_index(): void
    {
        $user = $this->userWithPermission('projects.view');

        for ($index = 0; $index < 16; $index++) {
            $this->createProject($user);
        }

        $response = $this->actingAs($user)
            ->get(route('projects.index'));

        $response->assertOk();

        $response->assertInertia(
            fn ($page) => $page
                ->component('Projects/Index')
                ->where('projects.current_page', 1)
                ->where('projects.per_page', 15)
                ->where('projects.last_page', 2)
                ->where('projects.total', 16)
                ->has('projects.data', 15)
        );
    }

    public function test_authorized_user_can_open_project_create_page_with_active_managers(): void
    {
        $creator = $this->userWithPermission('projects.create');

        $activeManager = User::factory()->create([
            'name' => 'Active Manager',
            'status' => 'active',
        ]);

        $inactiveManager = User::factory()->create([
            'name' => 'Inactive Manager',
            'status' => 'inactive',
        ]);

        $response = $this->actingAs($creator)
            ->get(route('projects.create'));

        $response->assertOk();

        $response->assertInertia(
            fn ($page) => $page
                ->component('Projects/Create')
                ->has('managers', 2)
                ->where('managers.0.id', $activeManager->id)
                ->where('managers.0.name', 'Active Manager')
        );

        $this->assertNotSame(
            $activeManager->id,
            $inactiveManager->id
        );
    }

    public function test_authorized_user_can_open_project_show_page_with_project_details(): void
    {
        $manager = $this->userWithPermission('projects.view');
        $member = User::factory()->create([
            'name' => 'Project Member',
            'status' => 'active',
        ]);

        $project = $this->createProject($manager);
        ProjectMember::query()->create([
            'project_id' => $project->id,
            'user_id' => $member->id,
            'project_role_id' => null,
        ]);

        $response = $this->actingAs($manager)
            ->get(route('projects.show', $project));

        $response->assertOk();

        $response->assertInertia(
            fn ($page) => $page
                ->component('Projects/Show')
                ->where('project.id', $project->id)
                ->where('project.name', 'HTTP Project')
                ->where('project.code', $project->code)
                ->where('project.manager.id', $manager->id)
                ->where('project.manager.name', $manager->name)
                ->has('project.members', 2)
        );
    }


    public function test_project_manager_can_add_member_through_http_layer(): void
    {
        $manager = $this->userWithPermission('projects.manage_members');
        $project = $this->createProject($manager);
        $member = User::factory()->create([
            'status' => 'active',
        ]);

        $this->actingAs($manager)
            ->post(route('projects.members.store', $project), [
                'user_id' => $member->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('project_user', [
            'project_id' => $project->id,
            'user_id' => $member->id,
            'project_role_id' => null,
        ]);
    }

    public function test_user_without_project_member_permission_cannot_add_member(): void
    {
        $manager = User::factory()->create([
            'status' => 'active',
        ]);
        $project = $this->createProject($manager);
        $member = User::factory()->create([
            'status' => 'active',
        ]);

        $this->actingAs($manager)
            ->post(route('projects.members.store', $project), [
                'user_id' => $member->id,
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('project_user', [
            'project_id' => $project->id,
            'user_id' => $member->id,
        ]);
    }

    public function test_project_manager_cannot_add_inactive_member(): void
    {
        $manager = $this->userWithPermission('projects.manage_members');
        $project = $this->createProject($manager);
        $member = User::factory()->create([
            'status' => 'inactive',
        ]);

        $this->actingAs($manager)
            ->post(route('projects.members.store', $project), [
                'user_id' => $member->id,
            ])
            ->assertSessionHasErrors('user_id');

        $this->assertDatabaseMissing('project_user', [
            'project_id' => $project->id,
            'user_id' => $member->id,
        ]);
    }

    public function test_project_manager_can_create_project_role_through_http_layer(): void
    {
        $manager = $this->userWithPermission('projects.manage_roles');
        $project = $this->createProject($manager);

        $this->actingAs($manager)
            ->post(route('projects.roles.store', $project), [
                'name' => 'Developer',
                'code' => 'developer',
                'description' => 'Project developer',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('project_roles', [
            'project_id' => $project->id,
            'name' => 'Developer',
            'code' => 'developer',
            'is_active' => true,
        ]);
    }

    public function test_user_without_project_role_permission_cannot_manage_project_roles(): void
    {
        $manager = User::factory()->create([
            'status' => 'active',
        ]);
        $project = $this->createProject($manager);

        $this->actingAs($manager)
            ->post(route('projects.roles.store', $project), [
                'name' => 'Developer',
                'code' => 'developer',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('project_roles', [
            'project_id' => $project->id,
            'code' => 'developer',
        ]);
    }

    public function test_project_manager_can_update_project_role_through_http_layer(): void
    {
        $manager = $this->userWithPermission('projects.manage_roles');
        $project = $this->createProject($manager);
        $role = $project->roles()->create([
            'name' => 'Developer',
            'code' => 'developer',
            'is_active' => true,
        ]);

        $this->actingAs($manager)
            ->put(route('projects.roles.update', [$project, $role]), [
                'name' => 'Senior Developer',
                'code' => 'senior_developer',
                'description' => 'Senior project developer',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('project_roles', [
            'id' => $role->id,
            'name' => 'Senior Developer',
            'code' => 'senior_developer',
            'description' => 'Senior project developer',
        ]);
    }

    public function test_project_manager_can_toggle_project_role_status_through_http_layer(): void
    {
        $manager = $this->userWithPermission('projects.manage_roles');
        $project = $this->createProject($manager);
        $role = $project->roles()->create([
            'name' => 'Developer',
            'code' => 'developer',
            'is_active' => true,
        ]);

        $this->actingAs($manager)
            ->patch(route('projects.roles.toggle-status', [$project, $role]))
            ->assertRedirect();

        $this->assertDatabaseHas('project_roles', [
            'id' => $role->id,
            'is_active' => false,
        ]);
    }

    public function test_project_manager_can_delete_unused_project_role_through_http_layer(): void
    {
        $manager = $this->userWithPermission('projects.manage_roles');
        $project = $this->createProject($manager);
        $role = $project->roles()->create([
            'name' => 'Developer',
            'code' => 'developer',
            'is_active' => true,
        ]);

        $this->actingAs($manager)
            ->delete(route('projects.roles.destroy', [$project, $role]))
            ->assertRedirect();

        $this->assertDatabaseMissing('project_roles', [
            'id' => $role->id,
        ]);
    }

    public function test_project_role_from_another_project_cannot_be_managed_through_nested_route(): void
    {
        $manager = $this->userWithPermission('projects.manage_roles');
        $project = $this->createProject($manager);
        $otherProject = $this->createProject();

        $role = $otherProject->roles()->create([
            'name' => 'Other Project Role',
            'code' => 'other_role',
            'is_active' => true,
        ]);

        $this->actingAs($manager)
            ->put(route('projects.roles.update', [$project, $role]), [
                'name' => 'Should Not Update',
                'code' => 'should_not_update',
                'description' => null,
            ])
            ->assertNotFound();

        $this->assertDatabaseHas('project_roles', [
            'id' => $role->id,
            'project_id' => $otherProject->id,
            'name' => 'Other Project Role',
            'code' => 'other_role',
        ]);
    }

    public function test_project_role_with_members_cannot_be_deleted_through_http_layer(): void
    {
        $manager = $this->userWithPermission('projects.manage_roles');
        $project = $this->createProject($manager);
        $role = $project->roles()->create([
            'name' => 'Developer',
            'code' => 'developer',
            'is_active' => true,
        ]);
        $member = User::factory()->create([
            'status' => 'active',
        ]);

        ProjectMember::query()->create([
            'project_id' => $project->id,
            'user_id' => $member->id,
            'project_role_id' => $role->id,
        ]);

        $this->actingAs($manager)
            ->delete(route('projects.roles.destroy', [$project, $role]))
            ->assertSessionHasErrors('role');

        $this->assertDatabaseHas('project_roles', [
            'id' => $role->id,
        ]);
    }

    public function test_authorized_user_can_create_project(): void
    {
        $creator = $this->userWithPermission('projects.create');
        $manager = User::factory()->create();

        $response = $this->actingAs($creator)->post(
            route('projects.store'),
            [
                'name' => 'HTTP Test Project',
                'code' => 'HTTP-PRJ-001',
                'description' => 'Created through HTTP layer.',
                'status' => 'planning',
                'manager_id' => $manager->id,
            ]
        );

        $response->assertRedirect();

        $this->assertDatabaseHas('projects', [
            'code' => 'HTTP-PRJ-001',
            'manager_id' => $manager->id,
            'created_by' => $creator->id,
        ]);

        $project = Project::query()
            ->where('code', 'HTTP-PRJ-001')
            ->firstOrFail();

        $this->assertDatabaseHas('project_user', [
            'project_id' => $project->id,
            'user_id' => $manager->id,
        ]);
    }

    public function test_user_without_project_view_permission_is_forbidden(): void
    {
        $user = User::factory()->create();
        $project = $this->createProject();

        $this->actingAs($user)
            ->get(route('projects.show', $project))
            ->assertForbidden();
    }

    public function test_project_manager_with_member_permission_receives_active_member_options(): void
    {
        $manager = $this->userWithPermission('projects.view');
        $manageMembersPermission = Permission::query()
            ->where('code', 'projects.manage_members')
            ->firstOrFail();
        $manager->roles()->first()->permissions()->attach($manageMembersPermission->id);

        $member = User::factory()->create([
            'name' => 'Available Member',
            'status' => 'active',
        ]);
        User::factory()->create([
            'name' => 'Inactive User',
            'status' => 'inactive',
        ]);

        $project = $this->createProject($manager);
        $response = $this->actingAs($manager)
            ->get(route('projects.show', $project));

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->component('Projects/Show')
                ->has('memberOptions', 1)
                ->where('memberOptions.0.id', $member->id)
                ->where('memberOptions.0.name', 'Available Member')
        );
    }

    public function test_project_manager_can_open_edit_page(): void
    {
        $manager = $this->userWithPermission('projects.update');
        $project = $this->createProject($manager);

        $response = $this->actingAs($manager)
            ->get(route('projects.edit', $project));

        $response->assertOk();

        $response->assertInertia(
            fn ($page) => $page
                ->component('Projects/Edit')
                ->where('project.id', $project->id)
                ->where('project.name', $project->name)
                ->where('project.code', $project->code)
        );
    }

    public function test_project_manager_can_update_project_through_http_layer(): void
    {
        $manager = $this->userWithPermission('projects.update');
        $project = $this->createProject($manager);

        $response = $this->actingAs($manager)->put(
            route('projects.update', $project),
            [
                'name' => 'Updated Project',
                'code' => $project->code,
                'description' => 'Updated description.',
                'status' => 'active',
                'start_date' => null,
                'end_date' => null,
                'is_active' => true,
            ]
        );

        $response->assertRedirect();

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'name' => 'Updated Project',
            'status' => 'active',
        ]);
    }

    public function test_project_manager_can_delete_project_through_http_layer(): void
    {
        $manager = $this->userWithPermission('projects.delete');
        $project = $this->createProject($manager);

        $this->actingAs($manager)
            ->delete(route('projects.destroy', $project))
            ->assertRedirect();

        $this->assertDatabaseMissing('projects', [
            'id' => $project->id,
        ]);
    }

    private function userWithPermission(string $permissionCode): User
    {
        $permission = Permission::query()
            ->where('code', $permissionCode)
            ->firstOrFail();

        $role = Role::query()->create([
            'name' => 'HTTP Project Role '.Str::upper(Str::random(8)),
            'code' => 'http-project-'.Str::lower(Str::random(12)),
            'description' => 'HTTP project test role',
            'is_active' => true,
            'is_system' => false,
        ]);

        $role->permissions()->attach($permission->id);

        $user = User::factory()->create([
            'status' => 'active',
        ]);

        $user->roles()->attach($role->id);

        return $user->fresh();
    }

    private function createProject(?User $manager = null): Project
    {
        $manager ??= User::factory()->create([
            'status' => 'active',
        ]);

        $project = Project::query()->create([
            'name' => 'HTTP Project',
            'code' => 'HTTP-'.Str::upper(Str::random(8)),
            'status' => 'active',
            'manager_id' => $manager->id,
            'created_by' => $manager->id,
            'is_active' => true,
        ]);

        ProjectMember::query()->create([
            'project_id' => $project->id,
            'user_id' => $manager->id,
            'project_role_id' => null,
        ]);

        return $project;
    }
}
