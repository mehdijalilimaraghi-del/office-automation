<?php

declare(strict_types=1);

namespace App\Modules\Cartable\Services;

use App\Models\User;
use App\Modules\Correspondence\Models\Correspondence;
use App\Modules\Correspondence\Services\CorrespondenceAccessService;
use App\Modules\Tasks\Models\Task;
use App\Modules\Tasks\Models\TaskAssignment;
use Illuminate\Database\Eloquent\Builder;

class CartableService
{
    public function for(User $user): array
    {
        return [
            'summary' => [
                'my_tasks' => $this->myTasksQuery($user)->count(),
                'pending_approvals' => $this->pendingApprovalsQuery($user)->count(),
                'referrals' => $this->referralsQuery($user)->count(),
                'correspondence_referrals' => $this->correspondenceActionsQuery($user)->count(),
            ],
            'myTasks' => $this->myTasksQuery($user)
                ->with(['project', 'department', 'assignee'])
                ->latest('updated_at')
                ->limit(10)
                ->get(),
            'pendingApprovals' => $this->pendingApprovalsQuery($user)
                ->with(['project', 'department', 'assignee'])
                ->latest('review_requested_at')
                ->limit(10)
                ->get(),
            'referrals' => $this->referralsQuery($user)
                ->with(['task.project', 'task.department', 'fromUser', 'assignedBy'])
                ->latest('created_at')
                ->limit(10)
                ->get(),
            'correspondenceItems' => $this->correspondenceActionsQuery($user)
                ->with(['currentDepartment:id,name'])
                ->latest('updated_at')
                ->limit(10)
                ->get(['id', 'type', 'classification', 'subject', 'registration_number', 'status', 'workflow_state', 'current_department_id', 'current_user_id', 'updated_at']),
        ];
    }

    private function myTasksQuery(User $user): Builder
    {
        return Task::query()
            ->where('assignee_id', $user->id)
            ->where('is_active', true)
            ->whereNotIn('status', ['completed', 'cancelled']);
    }

    private function pendingApprovalsQuery(User $user): Builder
    {
        return Task::query()
            ->where('is_active', true)
            ->where('status', 'submitted')
            ->where('review_status', 'pending')
            ->where(function (Builder $query) use ($user): void {
                $query
                    ->whereHas('department', fn (Builder $department) => $department->where('manager_id', $user->id))
                    ->orWhereHas('project', fn (Builder $project) => $project->where('manager_id', $user->id));
            });
    }

    private function referralsQuery(User $user): Builder
    {
        return TaskAssignment::query()
            ->where('to_user_id', $user->id)
            ->where('type', 'handoff')
            ->whereNull('ended_at');
    }

    private function correspondenceActionsQuery(User $user): Builder
    {
        return app(CorrespondenceAccessService::class)
            ->visibleToQuery(Correspondence::query(), $user)
            ->whereIn('status', [Correspondence::STATUS_DRAFT, Correspondence::STATUS_REGISTERED])
            ->whereIn('workflow_state', [
                'awaiting_manager_approval',
                'project_review_pending_manager',
                'project_request_approved',
                'result_pending_manager',
                'awaiting_dispatch',
                'in_progress',
                'assigned_to_employee',
                'task_requested',
                'project_requested',
                'letter_requested',
            ])
            ->where(function (Builder $query) use ($user): void {
                $query->where('current_user_id', $user->id)
                    ->orWhereHas('currentDepartment', fn (Builder $department) => $department->where('manager_id', $user->id));
            });
    }

}
