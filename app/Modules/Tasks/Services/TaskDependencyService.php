<?php

declare(strict_types=1);

namespace App\Modules\Tasks\Services;

use App\Modules\Tasks\Models\Task;
use App\Modules\Tasks\Models\TaskDependency;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TaskDependencyService
{
    public function add(Task $predecessor, Task $successor, string $type = 'completion'): TaskDependency
    {
        if ($predecessor->is($successor)) {
            throw new InvalidArgumentException('یک تسک نمی‌تواند وابستگی به خودش داشته باشد.');
        }

        if (! in_array($type, ['completion'], true)) {
            throw new InvalidArgumentException('نوع وابستگی تسک نامعتبر است.');
        }

        if ($this->wouldCreateCycle($predecessor, $successor)) {
            throw new InvalidArgumentException('این وابستگی باعث ایجاد چرخه بین تسک‌ها می‌شود.');
        }

        return TaskDependency::query()->firstOrCreate([
            'predecessor_task_id' => $predecessor->id,
            'successor_task_id' => $successor->id,
            'type' => $type,
        ]);
    }

    public function remove(TaskDependency $dependency): void
    {
        $dependency->delete();
    }

    public function canStart(Task $task): bool
    {
        return ! $task->predecessors()
            ->where(function ($query): void {
                $query->where('tasks.status', '!=', 'completed')
                    ->orWhere(function ($reviewQuery): void {
                        $reviewQuery
                            ->where('tasks.review_required', true)
                            ->where('tasks.review_status', '!=', 'approved');
                    });
            })
            ->exists();
    }

    private function wouldCreateCycle(Task $predecessor, Task $successor): bool
    {
        $visited = [];
        $stack = [$successor->id];

        while ($stack !== []) {
            $currentId = array_pop($stack);

            if (isset($visited[$currentId])) {
                continue;
            }

            $visited[$currentId] = true;

            if ($currentId === $predecessor->id) {
                return true;
            }

            $next = TaskDependency::query()
                ->where('predecessor_task_id', $currentId)
                ->pluck('successor_task_id')
                ->all();

            foreach ($next as $id) {
                $stack[] = (int) $id;
            }
        }

        return false;
    }
}
