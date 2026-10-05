<?php

declare(strict_types=1);

namespace App\Modules\Cartable\Services;

use App\Models\User;
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

}
