<?php

namespace Tests\Feature\Modules\Tasks;

use App\Models\User;
use App\Core\Departments\Models\Department;
use App\Modules\Projects\Models\ProjectSection;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectMember;
use App\Modules\Tasks\Contracts\TaskServiceContract;
use App\Modules\Tasks\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class TaskServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_task_can_be_created_for_project_member(): void
    {
        $creator = User::factory()->create();
        $project = $this->createProject([], $creator);
        $assignee = User::factory()->create();

        $this->addMember($project, $assignee);

        $task = app(TaskServiceContract::class)->create(
            [
                'project_id' => $project->id,
                'section_id' => $project->sections()->value('id'),
                'title' => 'Design database',
                'delegate_to_employee' => true,
                'delegate_assignee_id' => $assignee->id,
                'description' => 'Prepare database design',
                'assignee_id' => $assignee->id,
                'priority' => 'high',
                'start_date' => '2026-10-01',
                'due_date' => '2026-10-10',
            ],
            $creator
        );

        $this->assertInstanceOf(
            Task::class,
            $task
        );

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'project_id' => $project->id,
            'title' => 'Design database',
            'assignee_id' => $assignee->id,
            'created_by' => $creator->id,
            'status' => 'todo',
            'priority' => 'high',
            'is_active' => true,
        ]);
    }

    public function test_project_task_requires_a_section(): void
    {
        $creator = User::factory()->create();
        $project = $this->createProject([], $creator);
        $project->sections()->delete();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('برای ایجاد تسک در پروژه، انتخاب بخش الزامی است.');

        app(TaskServiceContract::class)->create([
            'project_id' => $project->id,
            'title' => 'Task without section',
        ], $creator);
    }

    public function test_task_can_be_created_without_a_project(): void
    {
        $creator = User::factory()->create();

        $task = app(TaskServiceContract::class)->create(
            [
                'project_id' => null,
                'title' => 'Standalone task',
                'assignee_id' => null,
            ],
            $creator
        );

        $this->assertNull($task->project_id);
        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'project_id' => null,
        ]);
    }

    public function test_task_delegate_must_be_member_of_section_department(): void
    {
        $creator = User::factory()->create();
        $project = $this->createProject([], $creator);
        $assignee = User::factory()->create();

        $this->expectException(
            InvalidArgumentException::class
        );

        app(TaskServiceContract::class)->create(
            [
                'project_id' => $project->id,
                'section_id' => $project->sections()->value('id'),
                'title' => 'Invalid assignment',
                'delegate_to_employee' => true,
                'delegate_assignee_id' => $assignee->id,
                'assignee_id' => $assignee->id,
            ],
            $creator
        );

        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_inactive_assignee_cannot_receive_task(): void
    {
        $creator = User::factory()->create();
        $project = $this->createProject([], $creator);

        $assignee = User::factory()->create([
            'status' => 'inactive',
        ]);

        $this->addMember($project, $assignee);

        $this->expectException(
            InvalidArgumentException::class
        );

        app(TaskServiceContract::class)->create(
            [
                'project_id' => $project->id,
                'section_id' => $project->sections()->value('id'),
                'title' => 'Inactive user task',
                'delegate_to_employee' => true,
                'delegate_assignee_id' => $assignee->id,
                'assignee_id' => $assignee->id,
            ],
            $creator
        );
    }

    public function test_task_cannot_be_created_in_inactive_project(): void
    {
        $creator = User::factory()->create();

        $project = $this->createProject(['is_active' => false], $creator);

        $assignee = User::factory()->create();

        $this->addMember($project, $assignee);

        $this->expectException(
            InvalidArgumentException::class
        );

        app(TaskServiceContract::class)->create(
            [
                'project_id' => $project->id,
                'section_id' => $project->sections()->value('id'),
                'title' => 'Inactive project task',
                'assignee_id' => $assignee->id,
            ],
            $creator
        );
    }

    public function test_task_due_date_cannot_be_before_start_date(): void
    {
        $creator = User::factory()->create();
        $project = $this->createProject([], $creator);
        $assignee = User::factory()->create();

        $this->addMember($project, $assignee);

        $this->expectException(
            InvalidArgumentException::class
        );

        app(TaskServiceContract::class)->create(
            [
                'project_id' => $project->id,
                'section_id' => $project->sections()->value('id'),
                'title' => 'Invalid dates',
                'assignee_id' => $assignee->id,
                'start_date' => '2026-12-31',
                'due_date' => '2026-10-01',
            ],
            $creator
        );
    }

    public function test_task_can_be_updated(): void
    {
        $creator = User::factory()->create();
        $project = $this->createProject([], $creator);
        $assignee = User::factory()->create();

        $this->addMember($project, $assignee);

        $task = app(TaskServiceContract::class)->create(
            [
                'project_id' => $project->id,
                'section_id' => $project->sections()->value('id'),
                'title' => 'Old title',
                'assignee_id' => $assignee->id,
            ],
            $creator
        );

        $updated = app(TaskServiceContract::class)->update(
            $task,
            [
                'title' => 'New title',
                'description' => 'Updated description',
                'priority' => 'urgent',
            ]
        );

        $this->assertSame(
            'New title',
            $updated->title
        );

        $this->assertSame(
            'urgent',
            $updated->priority
        );

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'title' => 'New title',
            'priority' => 'urgent',
        ]);
    }

    public function test_task_can_be_reassigned_to_another_project_member(): void
    {
        $creator = User::factory()->create();
        $project = $this->createProject([], $creator);

        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();

        $this->addMember($project, $firstUser);
        $this->addMember($project, $secondUser);

        $task = app(TaskServiceContract::class)->create(
            [
                'project_id' => $project->id,
                'section_id' => $project->sections()->value('id'),
                'title' => 'Reassign task',
                'assignee_id' => $firstUser->id,
            ],
            $creator
        );

        $updated = app(TaskServiceContract::class)->assign(
            $task,
            $secondUser
        );

        $this->assertEquals(
            $secondUser->id,
            $updated->assignee_id
        );
    }

    public function test_task_can_be_assigned_to_active_user_outside_project(): void
    {
        $creator = User::factory()->create();
        $project = $this->createProject([], $creator);
        $otherProject = $this->createProject([], $creator);

        $firstUser = User::factory()->create();
        $otherUser = User::factory()->create();

        $this->addMember($project, $firstUser);
        $this->addMember($otherProject, $otherUser);

        $task = app(TaskServiceContract::class)->create(
            [
                'project_id' => $project->id,
                'section_id' => $project->sections()->value('id'),
                'title' => 'Reassign task',
                'assignee_id' => $firstUser->id,
            ],
            $creator
        );

        $updated = app(TaskServiceContract::class)->assign(
            $task,
            $otherUser
        );

        $this->assertEquals($otherUser->id, $updated->assignee_id);
        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'assignee_id' => $otherUser->id,
        ]);
    }

    public function test_task_status_can_be_changed_to_completed(): void
    {
        $creator = User::factory()->create();
        $project = $this->createProject([], $creator);
        $assignee = User::factory()->create();

        $this->addMember($project, $assignee);

        $task = app(TaskServiceContract::class)->create(
            [
                'project_id' => $project->id,
                'section_id' => $project->sections()->value('id'),
                'title' => 'Complete task',
                'assignee_id' => $assignee->id,
            ],
            $creator
        );

        $updated = app(TaskServiceContract::class)
            ->changeStatus(
                $task,
                'completed'
            );

        $this->assertSame(
            'completed',
            $updated->status
        );

        $this->assertNotNull(
            $updated->completed_at
        );
    }

    public function test_completed_task_cannot_return_to_in_progress(): void
    {
        $creator = User::factory()->create();
        $project = $this->createProject([], $creator);
        $assignee = User::factory()->create();

        $this->addMember($project, $assignee);

        $task = app(TaskServiceContract::class)->create(
            [
                'project_id' => $project->id,
                'section_id' => $project->sections()->value('id'),
                'title' => 'Task',
                'assignee_id' => $assignee->id,
            ],
            $creator
        );

        $service = app(TaskServiceContract::class);

        $service->changeStatus(
            $task,
            'completed'
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('تسک نهایی‌شده امکان تغییر وضعیت ندارد.');

        $service->changeStatus(
            $task->refresh(),
            'in_progress'
        );
    }

    public function test_invalid_task_status_is_rejected(): void
    {
        $creator = User::factory()->create();
        $project = $this->createProject([], $creator);
        $assignee = User::factory()->create();

        $this->addMember($project, $assignee);

        $task = app(TaskServiceContract::class)->create(
            [
                'project_id' => $project->id,
                'section_id' => $project->sections()->value('id'),
                'title' => 'Task',
                'assignee_id' => $assignee->id,
            ],
            $creator
        );

        $this->expectException(
            InvalidArgumentException::class
        );

        app(TaskServiceContract::class)->changeStatus(
            $task,
            'unknown'
        );
    }

    private function createProject(
        array $overrides = [],
        ?User $creator = null,
    ): Project {
        $manager = User::factory()->create();

        $project = Project::query()->create(
            array_merge(
                [
                    'name' => 'Test Project',
                    'code' => 'PRJ-'.uniqid(),
                    'status' => 'active',
                    'manager_id' => $manager->id,
                    'created_by' => ($creator ?? $manager)->id,
                    'is_active' => true,
                ],
                $overrides
            )
        );

        ProjectMember::query()->create([
            'project_id' => $project->id,
            'user_id' => $manager->id,
            'project_role_id' => null,
        ]);

        $department = Department::query()->create([
            'name' => 'واحد آزمون '.uniqid(),
            'code' => 'TU-'.uniqid(),
            'manager_id' => $manager->id,
            'is_active' => true,
        ]);

        ProjectSection::query()->create([
            'project_id' => $project->id,
            'department_id' => $department->id,
            'name' => 'بخش آزمون',
            'manager_can_create_tasks' => true,
            'sort_order' => 0,
        ]);

        return $project;
    }

    private function addMember(
        Project $project,
        User $user
    ): ProjectMember {
        $membership = ProjectMember::query()->create([
            'project_id' => $project->id,
            'user_id' => $user->id,
            'project_role_id' => null,
        ]);

        $department = $project->sections()->first()?->department;
        if ($department !== null && ! $department->members()->where('users.id', $user->id)->exists()) {
            $department->members()->attach($user->id, ['is_primary' => false, 'joined_at' => now()]);
        }

        return $membership;
    }
}