<?php

namespace Tests\Feature\Modules\Projects;

use App\Models\User;
use App\Modules\Projects\Contracts\ProjectServiceContract;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class CreateProjectTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_can_be_created_and_manager_is_added_as_member(): void
    {
        $creator = User::factory()->create();
        $manager = User::factory()->create();

        $project = app(ProjectServiceContract::class)->create(
            [
                'name' => 'Test Project',
                'code' => 'PRJ-001',
                'description' => 'Test description',
                'status' => 'planning',
                'start_date' => '2026-10-01',
                'end_date' => '2026-12-31',
                'manager_id' => $manager->id,
            ],
            $creator
        );

        $this->assertInstanceOf(
            Project::class,
            $project
        );

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'name' => 'Test Project',
            'code' => 'PRJ-001',
            'manager_id' => $manager->id,
            'created_by' => $creator->id,
            'status' => 'planning',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('project_user', [
            'project_id' => $project->id,
            'user_id' => $manager->id,
            'project_role_id' => null,
        ]);
    }

    public function test_project_manager_must_be_active(): void
    {
        $creator = User::factory()->create();

        $inactiveManager = User::factory()->create([
            'status' => 'inactive',
        ]);

        $this->expectException(
            InvalidArgumentException::class
        );

        app(ProjectServiceContract::class)->create(
            [
                'name' => 'Test Project',
                'code' => 'PRJ-002',
                'manager_id' => $inactiveManager->id,
            ],
            $creator
        );

        $this->assertDatabaseCount('projects', 0);
    }

    public function test_project_manager_must_exist(): void
    {
        $creator = User::factory()->create();

        $this->expectException(
            InvalidArgumentException::class
        );

        app(ProjectServiceContract::class)->create(
            [
                'name' => 'Test Project',
                'code' => 'PRJ-003',
                'manager_id' => 999999,
            ],
            $creator
        );

        $this->assertDatabaseCount('projects', 0);
    }

    public function test_project_end_date_cannot_be_before_start_date(): void
    {
        $creator = User::factory()->create();
        $manager = User::factory()->create();

        $this->expectException(
            InvalidArgumentException::class
        );

        app(ProjectServiceContract::class)->create(
            [
                'name' => 'Test Project',
                'code' => 'PRJ-004',
                'manager_id' => $manager->id,
                'start_date' => '2026-12-31',
                'end_date' => '2026-10-01',
            ],
            $creator
        );

        $this->assertDatabaseCount('projects', 0);
    }

    public function test_project_creation_uses_creator_separately_from_manager(): void
    {
        $creator = User::factory()->create();
        $manager = User::factory()->create();

        $project = app(ProjectServiceContract::class)->create(
            [
                'name' => 'Test Project',
                'code' => 'PRJ-005',
                'manager_id' => $manager->id,
            ],
            $creator
        );

        $this->assertSame(
            $manager->id,
            $project->manager_id
        );

        $this->assertSame(
            $creator->id,
            $project->created_by
        );
    }

    public function test_manager_membership_does_not_require_a_project_role(): void
    {
        $creator = User::factory()->create();
        $manager = User::factory()->create();

        $project = app(ProjectServiceContract::class)->create(
            [
                'name' => 'Test Project',
                'code' => 'PRJ-006',
                'manager_id' => $manager->id,
            ],
            $creator
        );

        $membership = ProjectMember::query()
            ->where('project_id', $project->id)
            ->where('user_id', $manager->id)
            ->first();

        $this->assertNotNull($membership);

        $this->assertNull(
            $membership->project_role_id
        );
    }
}