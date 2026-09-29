<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Tasks;

use App\Models\User;
use App\Modules\Tasks\Actions\ChangeTaskStatusAction;
use App\Modules\Tasks\Models\Task;
use App\Modules\Tasks\Models\TaskStatusHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskStatusHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_change_creates_history_record(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $task = Task::query()->create([
            'project_id' => null,
            'department_id' => null,
            'title' => 'Status Test Task',
            'created_by' => $user->id,
            'assignee_id' => $user->id,
            'status' => 'todo',
            'priority' => 'normal',
        ]);

        $result = app(ChangeTaskStatusAction::class)->execute(
            task: $task,
            status: 'in_progress',
        );

        $this->assertSame('in_progress', $result->status);

        $this->assertDatabaseHas('task_status_histories', [
            'task_id' => $task->id,
            'from_status' => 'todo',
            'to_status' => 'in_progress',
            'changed_by' => $user->id,
        ]);
    }

    public function test_completing_task_sets_completed_at_and_records_history(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $task = Task::query()->create([
            'project_id' => null,
            'department_id' => null,
            'title' => 'Complete Test Task',
            'created_by' => $user->id,
            'assignee_id' => $user->id,
            'status' => 'in_progress',
            'priority' => 'normal',
        ]);

        app(ChangeTaskStatusAction::class)->execute(
            task: $task,
            status: 'completed',
        );

        $task->refresh();

        $this->assertNotNull($task->completed_at);

        $this->assertDatabaseHas('task_status_histories', [
            'task_id' => $task->id,
            'from_status' => 'in_progress',
            'to_status' => 'completed',
            'changed_by' => $user->id,
        ]);
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
        );

        $this->assertSame(
            0,
            TaskStatusHistory::query()->where('task_id', $task->id)->count()
        );
    }
}
