<?php

namespace Tests\Feature\Modules\Projects;

use App\Models\User;
use App\Modules\Projects\Contracts\ProjectMemberServiceContract;
use App\Modules\Projects\Contracts\ProjectRoleServiceContract;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectMember;
use App\Modules\Projects\Models\ProjectRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class ProjectRoleAndMemberTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_role_can_be_created(): void
    {
        $project = $this->createProject();

        $role = app(ProjectRoleServiceContract::class)->create(
            $project->id,
            [
                'name' => 'Developer',
                'code' => 'developer',
                'description' => 'Project developer',
            ]
        );

        $this->assertDatabaseHas('project_roles', [
            'id' => $role->id,
            'project_id' => $project->id,
            'name' => 'Developer',
            'is_active' => true,
        ]);
    }

    public function test_project_role_codes_are_generated_automatically_and_are_unique(): void
    {
        $project = $this->createProject();
        $service = app(ProjectRoleServiceContract::class);

        $first = $service->create($project->id, [
            'name' => 'Developer',
        ]);
        $second = $service->create($project->id, [
            'name' => 'Another Developer',
        ]);

        $this->assertMatchesRegularExpression('/^PRL-\\d{6}$/', $first->code);
        $this->assertMatchesRegularExpression('/^PRL-\\d{6}$/', $second->code);
        $this->assertNotSame($first->code, $second->code);
    }

    public function test_project_role_can_be_updated(): void
    {
        $project = $this->createProject();

        $role = ProjectRole::query()->create([
            'project_id' => $project->id,
            'name' => 'Developer',
            'code' => 'developer',
            'is_active' => true,
        ]);

        $updated = app(ProjectRoleServiceContract::class)->update(
            $role,
            [
                'name' => 'Senior Developer',
                'code' => 'senior_developer',
                'description' => 'Senior project developer',
            ]
        );

        $this->assertSame(
            'Senior Developer',
            $updated->name
        );

        $this->assertSame(
            'developer',
            $updated->code
        );

        $this->assertDatabaseHas('project_roles', [
            'id' => $role->id,
            'name' => 'Senior Developer',
            'code' => 'developer',
        ]);
    }

    public function test_project_role_status_can_be_toggled(): void
    {
        $project = $this->createProject();

        $role = ProjectRole::query()->create([
            'project_id' => $project->id,
            'name' => 'Developer',
            'code' => 'developer',
            'is_active' => true,
        ]);

        $service = app(ProjectRoleServiceContract::class);

        $service->toggleStatus($role);

        $this->assertDatabaseHas('project_roles', [
            'id' => $role->id,
            'is_active' => false,
        ]);

        $service->toggleStatus($role->refresh());

        $this->assertDatabaseHas('project_roles', [
            'id' => $role->id,
            'is_active' => true,
        ]);
    }

    public function test_project_role_can_be_deleted_when_unused(): void
    {
        $project = $this->createProject();

        $role = ProjectRole::query()->create([
            'project_id' => $project->id,
            'name' => 'Developer',
            'code' => 'developer',
            'is_active' => true,
        ]);

        app(ProjectRoleServiceContract::class)->delete($role);

        $this->assertDatabaseMissing('project_roles', [
            'id' => $role->id,
        ]);
    }

    public function test_project_role_with_members_cannot_be_deleted(): void
    {
        $project = $this->createProject();

        $role = ProjectRole::query()->create([
            'project_id' => $project->id,
            'name' => 'Developer',
            'code' => 'developer',
            'is_active' => true,
        ]);

        $member = User::factory()->create();

        ProjectMember::query()->create([
            'project_id' => $project->id,
            'user_id' => $member->id,
            'project_role_id' => $role->id,
        ]);

        $this->expectException(
            InvalidArgumentException::class
        );

        app(ProjectRoleServiceContract::class)->delete($role);
    }

    public function test_active_user_can_be_added_to_project(): void
    {
        $project = $this->createProject();
        $user = User::factory()->create();

        $member = app(ProjectMemberServiceContract::class)->add(
            projectId: $project->id,
            userId: $user->id
        );

        $this->assertDatabaseHas('project_user', [
            'id' => $member->id,
            'project_id' => $project->id,
            'user_id' => $user->id,
            'project_role_id' => null,
        ]);
    }

    public function test_inactive_user_cannot_be_added_to_project(): void
    {
        $project = $this->createProject();

        $user = User::factory()->create([
            'status' => 'inactive',
        ]);

        $this->expectException(
            InvalidArgumentException::class
        );

        app(ProjectMemberServiceContract::class)->add(
            projectId: $project->id,
            userId: $user->id
        );
    }

    public function test_duplicate_project_membership_is_rejected(): void
    {
        $project = $this->createProject();
        $user = User::factory()->create();

        $service = app(ProjectMemberServiceContract::class);

        $service->add(
            projectId: $project->id,
            userId: $user->id
        );

        $this->expectException(
            InvalidArgumentException::class
        );

        $service->add(
            projectId: $project->id,
            userId: $user->id
        );
    }

    public function test_member_can_be_added_with_project_role(): void
    {
        $project = $this->createProject();

        $role = ProjectRole::query()->create([
            'project_id' => $project->id,
            'name' => 'Developer',
            'code' => 'developer',
            'is_active' => true,
        ]);

        $user = User::factory()->create();

        $member = app(ProjectMemberServiceContract::class)->add(
            projectId: $project->id,
            userId: $user->id,
            projectRoleId: $role->id
        );

        $this->assertEquals(
            $role->id,
            $member->project_role_id
        );
    }

    public function test_role_from_another_project_cannot_be_assigned(): void
    {
        $project = $this->createProject();
        $otherProject = $this->createProject('PRJ-OTHER');

        $role = ProjectRole::query()->create([
            'project_id' => $otherProject->id,
            'name' => 'Developer',
            'code' => 'developer',
            'is_active' => true,
        ]);

        $user = User::factory()->create();

        $this->expectException(
            InvalidArgumentException::class
        );

        app(ProjectMemberServiceContract::class)->add(
            projectId: $project->id,
            userId: $user->id,
            projectRoleId: $role->id
        );
    }

    public function test_member_role_can_be_changed(): void
    {
        $project = $this->createProject();

        $firstRole = ProjectRole::query()->create([
            'project_id' => $project->id,
            'name' => 'Developer',
            'code' => 'developer',
            'is_active' => true,
        ]);

        $secondRole = ProjectRole::query()->create([
            'project_id' => $project->id,
            'name' => 'Reviewer',
            'code' => 'reviewer',
            'is_active' => true,
        ]);

        $user = User::factory()->create();

        $member = app(ProjectMemberServiceContract::class)->add(
            projectId: $project->id,
            userId: $user->id,
            projectRoleId: $firstRole->id
        );

        $updated = app(ProjectMemberServiceContract::class)
            ->changeRole(
                member: $member,
                projectRoleId: $secondRole->id
            );

        $this->assertEquals(
            $secondRole->id,
            $updated->project_role_id
        );
    }

    public function test_member_can_be_removed(): void
    {
        $project = $this->createProject();
        $user = User::factory()->create();

        $member = app(ProjectMemberServiceContract::class)->add(
            projectId: $project->id,
            userId: $user->id
        );

        app(ProjectMemberServiceContract::class)->remove(
            $member
        );

        $this->assertDatabaseMissing('project_user', [
            'id' => $member->id,
        ]);
    }

    public function test_project_manager_cannot_be_removed(): void
    {
        $manager = User::factory()->create();

        $project = $this->createProject(
            code: 'PRJ-MANAGER',
            manager: $manager
        );

        $member = ProjectMember::query()
            ->where('project_id', $project->id)
            ->where('user_id', $manager->id)
            ->firstOrFail();

        $this->expectException(
            InvalidArgumentException::class
        );

        app(ProjectMemberServiceContract::class)->remove(
            $member
        );
    }

    public function test_inactive_project_role_cannot_be_assigned(): void
    {
        $project = $this->createProject();

        $role = ProjectRole::query()->create([
            'project_id' => $project->id,
            'name' => 'Developer',
            'code' => 'developer',
            'is_active' => false,
        ]);

        $user = User::factory()->create();

        $this->expectException(
            InvalidArgumentException::class
        );

        app(ProjectMemberServiceContract::class)->add(
            projectId: $project->id,
            userId: $user->id,
            projectRoleId: $role->id
        );
    }

    private function createProject(
        string $code = 'PRJ-TEST',
        ?User $manager = null
    ): Project {
        $manager ??= User::factory()->create();

        $project = Project::query()->create([
            'name' => 'Test Project',
            'code' => $code,
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