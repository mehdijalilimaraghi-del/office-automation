<?php

namespace Tests\Feature\Modules\Projects;

use App\Models\User;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectMember;
use App\Modules\Projects\Models\ProjectRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ProjectModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_relationships_are_loaded_correctly(): void
    {
        $manager = User::factory()->create();
        $creator = User::factory()->create();
        $member = User::factory()->create();

        $project = Project::query()->create([
            'name' => 'Test Project',
            'code' => 'PRJ-001',
            'description' => 'Test description',
            'status' => 'planning',
            'start_date' => '2026-09-01',
            'end_date' => '2026-12-31',
            'manager_id' => $manager->id,
            'created_by' => $creator->id,
            'is_active' => true,
        ]);

        $role = ProjectRole::query()->create([
            'project_id' => $project->id,
            'name' => 'Developer',
            'code' => 'developer',
            'description' => 'Project developer',
            'is_active' => true,
        ]);

        $membership = ProjectMember::query()->create([
            'project_id' => $project->id,
            'user_id' => $member->id,
            'project_role_id' => $role->id,
        ]);

        $this->assertTrue(
            $project->manager->is($manager)
        );

        $this->assertTrue(
            $project->creator->is($creator)
        );

        $this->assertTrue(
            $project->roles->contains($role)
        );

        $this->assertTrue(
            $project->members->contains($membership)
        );

        $this->assertTrue(
            $role->project->is($project)
        );

        $this->assertTrue(
            $role->members->contains($membership)
        );

        $this->assertTrue(
            $membership->project->is($project)
        );

        $this->assertTrue(
            $membership->user->is($member)
        );

        $this->assertTrue(
            $membership->projectRole->is($role)
        );
    }

    public function test_project_users_many_to_many_relationship_works(): void
    {
        $manager = User::factory()->create();
        $member = User::factory()->create();

        $project = Project::query()->create([
            'name' => 'Test Project',
            'code' => 'PRJ-002',
            'status' => 'active',
            'manager_id' => $manager->id,
            'created_by' => $manager->id,
            'is_active' => true,
        ]);

        $role = ProjectRole::query()->create([
            'project_id' => $project->id,
            'name' => 'Developer',
            'code' => 'developer',
            'is_active' => true,
        ]);

        ProjectMember::query()->create([
            'project_id' => $project->id,
            'user_id' => $member->id,
            'project_role_id' => $role->id,
        ]);

        $project->load('users');

        $this->assertCount(1, $project->users);

        $this->assertTrue(
            $project->users->first()->is($member)
        );

        $this->assertEquals(
            $role->id,
            $project->users->first()->pivot->project_role_id
        );
    }

    public function test_user_project_relationships_work(): void
    {
        $manager = User::factory()->create();
        $member = User::factory()->create();

        $project = Project::query()->create([
            'name' => 'Test Project',
            'code' => 'PRJ-003',
            'status' => 'active',
            'manager_id' => $manager->id,
            'created_by' => $manager->id,
            'is_active' => true,
        ]);

        $role = ProjectRole::query()->create([
            'project_id' => $project->id,
            'name' => 'Developer',
            'code' => 'developer',
            'is_active' => true,
        ]);

        ProjectMember::query()->create([
            'project_id' => $project->id,
            'user_id' => $member->id,
            'project_role_id' => $role->id,
        ]);

        $member->load([
            'projects',
            'projectMemberships',
        ]);

        $this->assertCount(1, $member->projects);

        $this->assertTrue(
            $member->projects->first()->is($project)
        );

        $this->assertEquals(
            $role->id,
            $member->projects->first()->pivot->project_role_id
        );

        $this->assertCount(1, $member->projectMemberships);

        $this->assertTrue(
            $member->projectMemberships->first()->project->is($project)
        );
    }

    public function test_project_dates_are_cast_to_carbon(): void
    {
        $user = User::factory()->create();

        $project = Project::query()->create([
            'name' => 'Date Test',
            'code' => 'PRJ-004',
            'status' => 'planning',
            'start_date' => '2026-09-01',
            'end_date' => '2026-12-31',
            'manager_id' => $user->id,
            'created_by' => $user->id,
            'is_active' => true,
        ]);

        $project->refresh();

        $this->assertInstanceOf(
            Carbon::class,
            $project->start_date
        );

        $this->assertInstanceOf(
            Carbon::class,
            $project->end_date
        );

        $this->assertSame(
            '2026-09-01',
            $project->start_date->format('Y-m-d')
        );

        $this->assertSame(
            '2026-12-31',
            $project->end_date->format('Y-m-d')
        );
    }
}