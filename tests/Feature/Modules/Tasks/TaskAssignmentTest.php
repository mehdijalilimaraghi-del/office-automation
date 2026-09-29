<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Tasks;

use App\Models\User;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectMember;
use App\Modules\Tasks\Actions\AssignTaskAction;
use App\Modules\Tasks\Models\Task;
use App\Modules\Tasks\Models\TaskAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_initial_assignment_is_recorded_when_task_is_created(): void
    {
        $creator = User::factory()->create(['status' => 'active']);
        $assignee = User::factory()->create(['status' => 'active']);

        $task = Task::query()->create([
            'project_id' => null,
            'department_id' => null,
            'title' => 'Test Task',
            'created_by' => $creator->id,
            'assignee_id' => $assignee->id,
            'status' => 'todo',
            'priority' => 'normal',
        ]);

        TaskAssignment::query()->create([
            'task_id' => $task->id,
            'from_user_id' => null,
            'to_user_id' => $assignee->id,
            'assigned_by' => $creator->id,
            'type' => 'assignment',
            'started_at' => now(),
        ]);

        $this->assertDatabaseHas('task_assignments', [
            'task_id' => $task->id,
            'from_user_id' => null,
            'to_user_id' => $assignee->id,
            'assigned_by' => $creator->id,
            'type' => 'assignment',
        ]);
    }

    public function test_reassignment_closes_previous_assignment_and_creates_new_history(): void
    {
        $actor = User::factory()->create(['status' => 'active']);
        $oldAssignee = User::factory()->create(['status' => 'active']);
        $newAssignee = User::factory()->create(['status' => 'active']);

        $project = Project::query()->create([
            'name' => 'Assignment Test Project',
            'code' => 'ATP-'.uniqid(),
            'status' => 'active',
            'manager_id' => $actor->id,
            'created_by' => $actor->id,
            'is_active' => true,
        ]);

        foreach ([$actor, $oldAssignee, $newAssignee] as $user) {
            ProjectMember::query()->create([
                'project_id' => $project->id,
                'user_id' => $user->id,
                'project_role_id' => null,
            ]);
        }

        $task = Task::query()->create([
            'project_id' => $project->id,
            'department_id' => null,
            'title' => 'Assignment Test Task',
            'created_by' => $actor->id,
            'assignee_id' => $oldAssignee->id,
            'status' => 'todo',
            'priority' => 'normal',
        ]);

        $initial = TaskAssignment::query()->create([
            'task_id' => $task->id,
            'from_user_id' => null,
            'to_user_id' => $oldAssignee->id,
            'assigned_by' => $actor->id,
            'type' => 'assignment',
            'started_at' => now()->subMinute(),
        ]);

        app(AssignTaskAction::class)->execute(
            task: $task,
            assignee: $newAssignee,
            actor: $actor,
            type: 'handoff',
            note: 'ادامه کار توسط همکار جدید',
        );

        $initial->refresh();

        $this->assertNotNull($initial->ended_at);

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'assignee_id' => $newAssignee->id,
        ]);

        $this->assertDatabaseHas('task_assignments', [
            'task_id' => $task->id,
            'from_user_id' => $oldAssignee->id,
            'to_user_id' => $newAssignee->id,
            'assigned_by' => $actor->id,
            'type' => 'handoff',
            'note' => 'ادامه کار توسط همکار جدید',
        ]);

        $this->assertSame(
            2,
            TaskAssignment::query()->where('task_id', $task->id)->count()
        );
    }
}
