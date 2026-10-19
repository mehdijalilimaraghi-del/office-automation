<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Services;

use App\Models\User;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectSection;
use App\Modules\Tasks\Models\Task;
use App\Modules\Tasks\Services\TaskAccessService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

class DashboardService
{
    public function __construct(
        private readonly TaskAccessService $taskAccessService,
    ) {
    }

    public function for(User $user): array
    {
        $today = Carbon::today();
        $taskQuery = $this->taskAccessService->applyScope(Task::query(), $user, 'tasks.view');
        $personalTasks = (clone $taskQuery)->where('tasks.assignee_id', $user->id);
        $overduePersonalTasks = (clone $personalTasks)
            ->whereNotNull('tasks.due_date')
            ->whereDate('tasks.due_date', '<', $today)
            ->whereNotIn('tasks.status', ['completed', 'cancelled', 'cannot_continue']);

        $managedDepartmentIds = $user->managedDepartments()->pluck('departments.id')->map(fn ($id): int => (int) $id)->all();
        $managedProjectIds = $user->managedProjects()->pluck('projects.id')->map(fn ($id): int => (int) $id)->all();
        $hasOrganizationTaskScope = in_array('organization', $user->permissionScopes('tasks.view'), true);
        $canViewTeam = $hasOrganizationTaskScope || $managedDepartmentIds !== [] || $managedProjectIds !== [];

        $teamTasks = clone $taskQuery;
        if (! $hasOrganizationTaskScope) {
            $teamTasks->where(function (Builder $query) use ($managedDepartmentIds, $managedProjectIds): void {
                if ($managedDepartmentIds !== []) {
                    $query->whereIn('tasks.department_id', $managedDepartmentIds)
                        ->orWhereHas('section', fn (Builder $sectionQuery) => $sectionQuery->whereIn('department_id', $managedDepartmentIds));
                }

                if ($managedProjectIds !== []) {
                    $query->orWhereIn('tasks.project_id', $managedProjectIds);
                }

                if ($managedDepartmentIds === [] && $managedProjectIds === []) {
                    $query->whereRaw('1 = 0');
                }
            });
        }
        // Keep a manager's own assigned work exclusively in the personal panel.
        $teamTasks->where(fn (Builder $query) => $query->whereNull('tasks.assignee_id')->orWhere('tasks.assignee_id', '!=', $user->id));

        $activeProjects = $user->hasPermission('projects.view')
            ? Project::query()->where('is_active', true)->get()->filter(fn (Project $project): bool => Gate::forUser($user)->allows('view', $project))->count()
            : 0;

        $visibleProjects = $user->hasPermission('projects.view')
            ? Project::query()->where(fn ($query) => $query->where('is_system_default', false)->orWhere('is_active', true))->withCount('sections')->latest('updated_at')->get()
            : collect();
        $visibleProjects = $visibleProjects->filter(fn (Project $project): bool => Gate::forUser($user)->allows('view', $project));
        $projectIds = $visibleProjects->pluck('id');
        $visibleSections = ProjectSection::query()
            ->whereIn('project_id', $projectIds)
            ->with(['project:id,name,code', 'department:id,name'])
            ->withCount('tasks')
            ->get();

        $projectTaskCounts = (clone $taskQuery)->whereIn('tasks.project_id', $projectIds)
            ->selectRaw('project_id, COUNT(*) as total, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as completed', ['completed'])
            ->groupBy('project_id')->get()->keyBy('project_id');
        $sectionTaskCounts = (clone $taskQuery)->whereIn('tasks.project_id', $projectIds)->whereNotNull('tasks.section_id')
            ->selectRaw('section_id, COUNT(*) as total, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as completed', ['completed'])
            ->groupBy('section_id')->get()->keyBy('section_id');

        $trend = collect(range(6, 0))->map(function (int $daysAgo) use ($taskQuery): array {
            $day = Carbon::today()->subDays($daysAgo);
            $created = (clone $taskQuery)->whereDate('tasks.created_at', $day)->count();
            $completed = (clone $taskQuery)->whereNotNull('tasks.completed_at')->whereDate('tasks.completed_at', $day)->count();

            return [
                'date' => $day->format('Y-m-d'),
                'label' => $day->format('m/d'),
                'created' => $created,
                'completed' => $completed,
            ];
        })->values()->all();

        $mapTask = fn (Task $task): array => [
            'id' => $task->id,
            'title' => $task->title,
            'status' => $task->status,
            'sub_status' => $task->sub_status,
            'priority' => $task->priority,
            'due_date' => $task->due_date?->format('Y-m-d'),
            'project' => $task->project?->name,
            'assignee' => $task->assignee?->name,
        ];

        $personalTaskRows = (clone $personalTasks)->with(['project', 'assignee'])->whereNotIn('tasks.status', ['completed', 'cancelled', 'cannot_continue'])
            ->orderByRaw('CASE WHEN tasks.due_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('tasks.due_date')->latest('tasks.updated_at')->limit(6)->get()->map($mapTask)->values()->all();

        $teamTaskRows = (clone $teamTasks)->with(['project', 'assignee'])
            ->whereNotIn('tasks.status', ['completed', 'cancelled', 'cannot_continue'])
            ->orderByRaw('CASE WHEN tasks.due_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('tasks.due_date')->latest('tasks.updated_at')->limit(8)->get()->map($mapTask)->values()->all();

        return [
            'canViewTeam' => $canViewTeam,
            'stats' => [
                'my_tasks' => (clone $personalTasks)->whereNotIn('tasks.status', ['completed', 'cancelled', 'cannot_continue'])->count(),
                'my_today_tasks' => (clone $personalTasks)->whereDate('tasks.due_date', $today)->whereNotIn('tasks.status', ['completed', 'cancelled', 'cannot_continue'])->count(),
                'my_overdue_tasks' => $overduePersonalTasks->count(),
                'my_review_tasks' => (clone $personalTasks)->where('tasks.sub_status', 'manager_review')->count(),
                'team_active_tasks' => (clone $teamTasks)->whereNotIn('tasks.status', ['completed', 'cancelled', 'cannot_continue'])->count(),
                'team_overdue_tasks' => (clone $teamTasks)->whereNotNull('tasks.due_date')->whereDate('tasks.due_date', '<', $today)->whereNotIn('tasks.status', ['completed', 'cancelled', 'cannot_continue'])->count(),
                'team_review_tasks' => (clone $teamTasks)->where('tasks.sub_status', 'manager_review')->count(),
                'team_completed_month' => (clone $teamTasks)->where('tasks.status', 'completed')->whereNotNull('tasks.completed_at')->whereDate('tasks.completed_at', '>=', Carbon::now()->startOfMonth()->toDateString())->count(),
                'active_projects' => $activeProjects,
                'projects_total' => $visibleProjects->count(),
                'projects_planning' => $visibleProjects->where('status', 'planning')->count(),
                'projects_completed' => $visibleProjects->where('status', 'completed')->count(),
                'sections_total' => $visibleSections->count(),
                'tasks_total' => (clone $taskQuery)->whereIn('tasks.project_id', $projectIds)->count(),
                'tasks_todo' => (clone $taskQuery)->whereIn('tasks.project_id', $projectIds)->whereIn('tasks.status', ['todo', 'observed'])->count(),
                'tasks_in_progress' => (clone $taskQuery)->whereIn('tasks.project_id', $projectIds)->where('tasks.status', 'in_progress')->count(),
                'tasks_completed' => (clone $taskQuery)->whereIn('tasks.project_id', $projectIds)->where('tasks.status', 'completed')->count(),
                'tasks_overdue' => (clone $taskQuery)->whereIn('tasks.project_id', $projectIds)->whereNotNull('tasks.due_date')->whereDate('tasks.due_date', '<', $today)->whereNotIn('tasks.status', ['completed', 'cancelled', 'cannot_continue'])->count(),
                'tasks_review' => (clone $taskQuery)->whereIn('tasks.project_id', $projectIds)->where('tasks.sub_status', 'manager_review')->count(),
            ],
            'trend' => $trend,
            'personalTasks' => $personalTaskRows,
            'teamTasks' => $teamTaskRows,
            'projectsOverview' => $visibleProjects->map(fn (Project $project): array => [
                'id' => $project->id,
                'name' => $project->name,
                'code' => $project->code,
                'status' => $project->status,
                'sections_count' => $project->sections_count,
                'tasks_count' => (int) ($projectTaskCounts->get($project->id)->total ?? 0),
                'progress' => (int) (($projectTaskCounts->get($project->id)->total ?? 0) > 0 ? round(($projectTaskCounts->get($project->id)->completed ?? 0) * 100 / $projectTaskCounts->get($project->id)->total) : 0),
            ])->values()->all(),
            'sectionsOverview' => $visibleSections->map(function ($section) use ($sectionTaskCounts): array {
                $counts = $sectionTaskCounts->get($section->id);
                $total = (int) ($counts->total ?? 0);
                $completed = (int) ($counts->completed ?? 0);

                return [
                    'id' => $section->id,
                    'name' => $section->name,
                    'project_id' => $section->project_id,
                    'project_name' => $section->project?->name,
                    'project_code' => $section->project?->code,
                    'department' => $section->department?->name,
                    'tasks_count' => $total,
                    'completed_tasks' => $completed,
                    'progress' => $total > 0 ? (int) round($completed * 100 / $total) : 0,
                ];
            })->sortByDesc('tasks_count')->take(8)->values()->all(),
        ];
    }
}
