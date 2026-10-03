<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Services;

use App\Models\User;
use App\Modules\Projects\Models\Project;
use App\Modules\Tasks\Models\Task;
use App\Modules\Tasks\Services\TaskAccessService;
use Illuminate\Support\Carbon;

class DashboardService
{
    public function __construct(
        private readonly TaskAccessService $taskAccessService,
    ) {
    }

    public function for(User $user): array
    {
        $today = Carbon::today();

        $taskQuery = $this->taskAccessService
            ->applyScope(Task::query(), $user, 'tasks.view');

        $myTasks = (clone $taskQuery)->where('tasks.assignee_id', $user->id);
        $todayTasks = (clone $taskQuery)->whereDate('tasks.due_date', $today);
        $overdueTasks = (clone $taskQuery)
            ->whereNotNull('tasks.due_date')
            ->whereDate('tasks.due_date', '<', $today)
            ->where('tasks.status', '!=', 'completed');
        $completedTasks = (clone $taskQuery)->where('tasks.status', 'completed');

        $activeProjects = $user->hasPermission('projects.view')
            ? Project::query()->where('is_active', true)->count()
            : 0;

        return [
            'stats' => [
                'my_tasks' => $myTasks->count(),
                'today_tasks' => $todayTasks->count(),
                'overdue_tasks' => $overdueTasks->count(),
                'active_projects' => $activeProjects,
                'completed_tasks' => $completedTasks->count(),
            ],
            'recentTasks' => (clone $taskQuery)
                ->with(['project', 'assignee'])
                ->latest('updated_at')
                ->limit(8)
                ->get()
                ->map(fn (Task $task): array => [
                    'id' => $task->id,
                    'title' => $task->title,
                    'status' => $task->status,
                    'priority' => $task->priority,
                    'due_date' => $task->due_date?->format('Y-m-d'),
                    'project' => $task->project?->name,
                    'assignee' => $task->assignee?->name,
                ])
                ->values()
                ->all(),
        ];
    }
}
