<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Tasks;

use App\Models\User;
use App\Core\Departments\Models\Department;
use App\Modules\Tasks\Actions\ChangeTaskStatusAction;
use App\Modules\Tasks\Models\Task;
use App\Modules\Tasks\Models\TaskStatusHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TaskStatusHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_final_status_change_creates_history_record(): void
    {
        $manager = User::factory()->create(['status' => 'active']);
        $department = Department::query()->create([
            'name' => 'Status Test Unit',
            'code' => 'STS-'.Str::upper(Str::random(6)),
            'manager_id' => $manager->id,
            'is_active' => true,
        ]);

        $task = Task::query()->create([
            'department_id' => $department->id,
            'title' => 'Status Test Task',
            'created_by' => $manager->id,
            'assignee_id' => $manager->id,
            'status' => 'todo',
            'priority' => 'normal',
        ]);

        $result = app(ChangeTaskStatusAction::class)->execute(
            task: $task,
            status: 'cancelled',
            actor: $manager,
        );

        $this->assertSame('cancelled', $result->status);

        $this->assertDatabaseHas('task_status_histories', [
            'task_id' => $task->id,
            'from_status' => 'todo',
            'to_status' => 'cancelled',
            'changed_by' => $manager->id,
        ]);
    }

    public function test_completion_cannot_be_set_through_manual_status_change(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $task = Task::query()->create([
            'title' => 'Complete Test Task',
            'created_by' => $user->id,
            'assignee_id' => $user->id,
            'status' => 'in_progress',
            'priority' => 'normal',
        ]);

        $this->expectException(\InvalidArgumentException::class);

        app(ChangeTaskStatusAction::class)->execute(
            task: $task,
            status: 'completed',
            actor: $user,
        );
    }

    public function test_invalid_status_is_rejected(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $task = Task::query()->create([
            'project_id' => null,
            'department_id' => null,
            'title' => 'Invalid Status Task',
            'created_by' => $user->id,
            'assignee_id' => $user->id,
            'status' => 'todo',
            'priority' => 'normal',
        ]);

        $this->expectException(\InvalidArgumentException::class);

        app(ChangeTaskStatusAction::class)->execute(
            task: $task,
            status: 'invalid',
            actor: $user,
        );

        $this->assertSame(
            0,
            TaskStatusHistory::query()->where('task_id', $task->id)->count()
        );
    }
}
