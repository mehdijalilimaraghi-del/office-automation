<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Tasks;

use App\Models\User;
use App\Modules\Tasks\Models\Task;
use App\Modules\Tasks\Services\TaskDependencyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskDependencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_completion_dependency_blocks_successor_until_predecessor_is_completed(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $first = Task::query()->create([
            'title' => 'First task',
            'status' => 'in_progress',
            'priority' => 'normal',
            'created_by' => $user->id,
        ]);

        $second = Task::query()->create([
            'title' => 'Second task',
            'status' => 'todo',
            'priority' => 'normal',
            'created_by' => $user->id,
        ]);

        $service = app(TaskDependencyService::class);
        $dependency = $service->add($first, $second);

        $this->assertDatabaseHas('task_dependencies', [
            'id' => $dependency->id,
            'predecessor_task_id' => $first->id,
            'successor_task_id' => $second->id,
            'type' => 'completion',
        ]);

        $this->assertFalse($service->canStart($second));

        $first->update(['status' => 'completed']);

        $this->assertTrue($service->canStart($second));
    }

    public function test_dependency_cycle_is_rejected(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $first = Task::query()->create([
            'title' => 'Cycle A',
            'status' => 'todo',
            'priority' => 'normal',
            'created_by' => $user->id,
        ]);

        $second = Task::query()->create([
            'title' => 'Cycle B',
            'status' => 'todo',
            'priority' => 'normal',
            'created_by' => $user->id,
        ]);

        $third = Task::query()->create([
            'title' => 'Cycle C',
            'status' => 'todo',
            'priority' => 'normal',
            'created_by' => $user->id,
        ]);

        $service = app(TaskDependencyService::class);
        $service->add($first, $second);
        $service->add($second, $third);

        $this->expectException(\InvalidArgumentException::class);
        $service->add($third, $first);
    }

    public function test_self_dependency_is_rejected(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $task = Task::query()->create([
            'title' => 'Self dependency',
            'status' => 'todo',
            'priority' => 'normal',
            'created_by' => $user->id,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        app(TaskDependencyService::class)->add($task, $task);
    }
}
