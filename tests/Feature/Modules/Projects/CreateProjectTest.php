<?php

namespace Tests\Feature\Modules\Projects;

use App\Models\User;
use App\Core\Departments\Models\Department;
use App\Modules\Projects\Contracts\ProjectServiceContract;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectMember;
use App\Modules\Projects\Models\ProjectSection;
use App\Modules\Tasks\Contracts\TaskServiceContract;
use App\Modules\Tasks\Models\TaskAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class CreateProjectTest extends TestCase
{
    use RefreshDatabase;

    private function addProjectManagementMembership(User $user): void
    {
        $parent = Department::query()->firstOrCreate(
            ['name' => 'مدیریت'],
            ['code' => 'MGMT', 'is_active' => true],
        );

        $department = Department::query()->firstOrCreate(
            ['name' => 'مدیریت پروژه', 'parent_id' => $parent->id],
            ['code' => 'PROJECT-MGMT', 'is_active' => true],
        );

        $department->members()->syncWithoutDetaching([
            $user->id => [
                'is_primary' => false,
                'joined_at' => now(),
            ],
        ]);
    }

    public function test_project_can_be_created_without_legacy_project_membership(): void
    {
        $creator = User::factory()->create();
        $manager = User::factory()->create();
        $this->addProjectManagementMembership($manager);

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
            'manager_id' => $manager->id,
            'created_by' => $creator->id,
            'status' => 'planning',
            'is_active' => true,
        ]);

        $this->assertDatabaseMissing('project_user', [
            'project_id' => $project->id,
            'user_id' => $manager->id,
        ]);
    }

    public function test_project_department_is_persisted_when_provided(): void
    {
        $creator = User::factory()->create();
        $manager = User::factory()->create();
        $this->addProjectManagementMembership($manager);
        $department = \App\Core\Departments\Models\Department::query()->create([
            'name' => 'فناوری اطلاعات',
            'code' => 'IT',
            'is_active' => true,
        ]);

        $project = app(ProjectServiceContract::class)->create(
            [
                'department_id' => $department->id,
                'name' => 'Department Project',
                'code' => 'PRJ-DEPT-001',
                'manager_id' => $manager->id,
            ],
            $creator
        );

        $this->assertSame($department->id, $project->department_id);
        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'department_id' => $department->id,
            'is_system_default' => false,
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
        $this->addProjectManagementMembership($manager);

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

    public function test_project_manager_cannot_be_changed_through_project_update(): void
    {
        $creator = User::factory()->create();
        $manager = User::factory()->create();
        $this->addProjectManagementMembership($manager);
        $replacement = User::factory()->create();

        $project = app(ProjectServiceContract::class)->create(
            [
                'name' => 'Managed Project',
                'code' => 'PRJ-MANAGER-LOCK',
                'manager_id' => $manager->id,
            ],
            $creator
        );

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(ProjectServiceContract::class)->update(
            $project,
            [
                'name' => 'Managed Project',
                'code' => 'PRJ-MANAGER-LOCK',
                'status' => 'planning',
                'manager_id' => $replacement->id,
            ]
        );
    }

    public function test_project_creation_does_not_create_legacy_project_membership(): void
    {
        $creator = User::factory()->create();
        $manager = User::factory()->create();
        $this->addProjectManagementMembership($manager);

        $project = app(ProjectServiceContract::class)->create(
            [
                'name' => 'Test Project',
                'code' => 'PRJ-006',
                'manager_id' => $manager->id,
            ],
            $creator
        );

        $this->assertFalse(
            ProjectMember::query()
                ->where('project_id', $project->id)
                ->where('user_id', $manager->id)
                ->exists()
        );
    }

    public function test_project_can_be_created_with_department_sections_and_manager_task_setting(): void
    {
        $creator = User::factory()->create();
        $manager = User::factory()->create();
        $this->addProjectManagementMembership($manager);
        $department = Department::query()->create([
            'name' => 'واحد طراحی',
            'code' => 'DESIGN',
            'manager_id' => $manager->id,
            'is_active' => true,
        ]);

        $project = app(ProjectServiceContract::class)->create([
            'name' => 'Product Delivery',
            'manager_id' => $manager->id,
            'sections' => [
                [
                    'department_id' => $department->id,
                    'name' => 'طراحی محصول',
                    'description' => 'طراحی قطعات محصول',
                    'manager_can_create_tasks' => false,
                ],
            ],
        ], $creator);

        $section = ProjectSection::query()->where('project_id', $project->id)->firstOrFail();

        $this->assertEquals('طراحی محصول', $section->name);
        $this->assertEquals($department->id, $section->department_id);
        $this->assertFalse($section->manager_can_create_tasks);
        $this->assertDatabaseHas('project_sections', [
            'project_id' => $project->id,
            'department_id' => $department->id,
            'name' => 'طراحی محصول',
            'manager_can_create_tasks' => false,
        ]);
    }

    public function test_section_task_is_initially_assigned_to_unit_manager_then_referred_to_employee(): void
    {
        $projectAdmin = User::factory()->create();
        $this->addProjectManagementMembership($projectAdmin);
        $unitManager = User::factory()->create();
        $employee = User::factory()->create();

        $department = Department::query()->create([
            'name' => 'واحد طراحی',
            'code' => 'DESIGN',
            'manager_id' => $unitManager->id,
            'is_active' => true,
        ]);
        $department->members()->attach($employee->id, [
            'is_primary' => false,
            'joined_at' => now(),
        ]);

        $project = app(ProjectServiceContract::class)->create([
            'name' => 'Product Delivery',
            'manager_id' => $projectAdmin->id,
            'sections' => [[
                'department_id' => $department->id,
                'name' => 'طراحی محصول',
                'manager_can_create_tasks' => true,
            ]],
        ], $projectAdmin);
        $section = ProjectSection::query()->where('project_id', $project->id)->firstOrFail();

        $task = app(TaskServiceContract::class)->create([
            'project_id' => $project->id,
            'section_id' => $section->id,
            'title' => 'طراحی قطعه A',
            'delegate_to_employee' => true,
            'delegate_assignee_id' => $employee->id,
        ], $projectAdmin);

        $this->assertEquals($employee->id, $task->assignee_id);
        $this->assertEquals($department->id, $task->department_id);
        $this->assertSame(2, TaskAssignment::query()->where('task_id', $task->id)->count());
        $this->assertDatabaseHas('task_assignments', [
            'task_id' => $task->id,
            'to_user_id' => $unitManager->id,
            'type' => 'assignment',
        ]);
        $this->assertDatabaseHas('task_assignments', [
            'task_id' => $task->id,
            'from_user_id' => $unitManager->id,
            'to_user_id' => $employee->id,
            'assigned_by' => $projectAdmin->id,
            'type' => 'referral',
        ]);
    }
}