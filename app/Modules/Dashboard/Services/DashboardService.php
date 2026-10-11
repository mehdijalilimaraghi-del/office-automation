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
            ? Project::query()->where('is_active', true)->get()->filter(fn (Project $project): bool => \Illuminate\Support\Facades\Gate::forUser($user)->allows('view', $project))->count()
            : 0;

        $visibleProjects = $user->hasPermission('projects.view') ? Project::query()->withCount(['sections'])->latest('updated_at')->limit(100)->get() : collect();
        $visibleProjects = $visibleProjects->filter(fn (Project $project): bool => \Illuminate\Support\Facades\Gate::forUser($user)->allows('view', $project));
        $projectIds = $visibleProjects->pluck('id');
        $visibleSections = \App\Modules\Projects\Models\ProjectSection::query()->whereIn('project_id', $projectIds)->with(['project:id,name,code', 'department:id,name'])->withCount('tasks')->get();
        $projectTaskCounts = (clone $taskQuery)->whereIn('tasks.project_id', $projectIds)->selectRaw('project_id, COUNT(*) as total')->groupBy('project_id')->get()->keyBy('project_id');
        $sectionTaskCounts = (clone $taskQuery)->whereIn('tasks.project_id', $projectIds)->whereNotNull('tasks.section_id')->selectRaw('section_id, COUNT(*) as total, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as completed', ['completed'])->groupBy('section_id')->get()->keyBy('section_id');

        return [
            'stats' => [
                'my_tasks' => $myTasks->count(),
                'today_tasks' => $todayTasks->count(),
                'overdue_tasks' => $overdueTasks->count(),
                'active_projects' => $activeProjects,
                'completed_tasks' => $completedTasks->count(),
                'projects_total' => $visibleProjects->count(),
                'projects_planning' => $visibleProjects->where('status', 'planning')->count(),
                'projects_completed' => $visibleProjects->where('status', 'completed')->count(),
                'sections_total' => $visibleSections->count(),
                'tasks_total' => (clone $taskQuery)->whereIn('tasks.project_id', $projectIds)->count(),
                'tasks_todo' => (clone $taskQuery)->whereIn('tasks.project_id', $projectIds)->where('tasks.status', 'todo')->count(),
                'tasks_in_progress' => (clone $taskQuery)->whereIn('tasks.project_id', $projectIds)->where('tasks.status', 'in_progress')->count(),
                'tasks_review' => (clone $taskQuery)->whereIn('tasks.project_id', $projectIds)->where('tasks.review_status', 'pending')->count(),
            ],
            'projectsOverview' => $visibleProjects->map(fn (Project $project): array => [
                'id' => $project->id, 'name' => $project->name, 'code' => $project->code, 'status' => $project->status,
                'sections_count' => $project->sections_count, 'tasks_count' => (int) ($projectTaskCounts->get($project->id)->total ?? 0),
            ])->values()->all(),
            'sectionsOverview' => $visibleSections->map(function ($section) use ($sectionTaskCounts): array {
                $counts = $sectionTaskCounts->get($section->id);
                $total = (int) ($counts->total ?? 0);
                $completed = (int) ($counts->completed ?? 0);
                return ['id' => $section->id, 'name' => $section->name, 'project_id' => $section->project_id,
                    'project_name' => $section->project?->name, 'project_code' => $section->project?->code,
                    'department' => $section->department?->name, 'tasks_count' => $total,
                    'completed_tasks' => $completed, 'progress' => $total > 0 ? (int) round($completed * 100 / $total) : 0];
            })->sortByDesc('tasks_count')->take(8)->values()->all(),
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
