<?php

declare(strict_types=1);

namespace App\Modules\Tasks\Services;

use App\Core\Departments\Models\Department;
use App\Core\Permissions\Enums\PermissionScope;
use App\Models\User;
use App\Modules\Tasks\Models\Task;
use Illuminate\Database\Eloquent\Builder;

class TaskAccessService
{
    public function canAccess(
        User $user,
        Task $task,
        string $permission
    ): bool {
        if (! $user->isActive()) {
            return false;
        }

        return $this->hasScopedAccess(
            $user,
            $task,
            $permission
        );
    }

    public function applyScope(
        Builder $query,
        User $user,
        string $permission
    ): Builder {
        if (! $user->isActive()) {
            return $query->whereRaw('1 = 0');
        }

        $scopes = $user->permissionScopes($permission);

        if ($scopes === []) {
            return $query->whereRaw('1 = 0');
        }

        if (in_array(
            PermissionScope::Organization->value,
            $scopes,
            true
        )) {
            return $query;
        }

        return $query->where(function (Builder $scopeQuery) use (
            $user,
            $scopes
        ): void {
            if ($this->hasScope($scopes, PermissionScope::Self)) {
                $this->applySelfScope($scopeQuery, $user);
            }

            if ($this->hasScope($scopes, PermissionScope::Department)) {
                $this->applyDepartmentScope($scopeQuery, $user);
            }

            if ($this->hasScope($scopes, PermissionScope::DepartmentTree)) {
                $this->applyDepartmentTreeScope($scopeQuery, $user);
            }

            if ($this->hasScope($scopes, PermissionScope::Project)) {
                $this->applyProjectScope($scopeQuery, $user);
            }
        });
    }

    private function hasScopedAccess(
        User $user,
        Task $task,
        string $permission
    ): bool {
        $scopes = $user->permissionScopes($permission);

        if ($scopes === []) {
            return false;
        }

        foreach ($scopes as $scope) {
            if ($scope === PermissionScope::Organization->value) {
                return true;
            }

            if (
                $scope === PermissionScope::Self->value
                && $this->isSelfTask($user, $task)
            ) {
                return true;
            }

            if (
                $scope === PermissionScope::Department->value
                && $this->belongsToUserDepartment($user, $task)
            ) {
                return true;
            }

            if (
                $scope === PermissionScope::DepartmentTree->value
                && $this->belongsToUserDepartmentTree($user, $task)
            ) {
                return true;
            }

            if (
                $scope === PermissionScope::Project->value
                && (
                    $this->isSelfTask($user, $task)
                    || $this->hasProjectAccess($user, $task)
                )
            ) {
                return true;
            }
        }

        return false;
    }

    private function applySelfScope(
        Builder $query,
        User $user
    ): void {
        $query->where(function (Builder $scopeQuery) use ($user): void {
            $scopeQuery
                ->where('tasks.assignee_id', $user->id)
                ->orWhere('tasks.created_by', $user->id);
        });
    }

    private function applyDepartmentScope(
        Builder $query,
        User $user
    ): void {
        $departmentIds = $user->departments()
            ->pluck('departments.id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($departmentIds === []) {
            return;
        }

        $query->orWhereIn('tasks.department_id', $departmentIds);
    }

    private function applyDepartmentTreeScope(
        Builder $query,
        User $user
    ): void {
        $departmentIds = $this->userDepartmentTreeIds($user);

        if ($departmentIds === []) {
            return;
        }

        $query->orWhereIn('tasks.department_id', $departmentIds);
    }

    private function applyProjectScope(
        Builder $query,
        User $user
    ): void {
        $query->orWhere('tasks.assignee_id', $user->id)
            ->orWhere('tasks.created_by', $user->id)
            ->orWhereHas(
                'project',
                function (Builder $projectQuery) use ($user): void {
                    $projectQuery
                        ->where('projects.manager_id', $user->id)
                        ->orWhereHas(
                            'members',
                            fn (Builder $memberQuery) => $memberQuery
                                ->where('users.id', $user->id)
                        );
                }
            );
    }

    private function isSelfTask(User $user, Task $task): bool
    {
        return (int) $task->assignee_id === (int) $user->id
            || (int) $task->created_by === (int) $user->id;
    }

    private function belongsToUserDepartment(
        User $user,
        Task $task
    ): bool {
        if ($task->department_id === null) {
            return false;
        }

        return $user->departments()
            ->where('departments.id', $task->department_id)
            ->exists();
    }

    private function belongsToUserDepartmentTree(
        User $user,
        Task $task
    ): bool {
        if ($task->department_id === null) {
            return false;
        }

        return in_array(
            (int) $task->department_id,
            $this->userDepartmentTreeIds($user),
            true
        );
    }

    private function userDepartmentTreeIds(User $user): array
    {
        $ids = [];

        $departments = $user->departments()->get();

        foreach ($departments as $department) {
            $ids[] = (int) $department->id;

            foreach ($department->descendantIds() as $descendantId) {
                $ids[] = (int) $descendantId;
            }
        }

        return array_values(array_unique($ids));
    }

    private function hasProjectAccess(
        User $user,
        Task $task
    ): bool {
        if ($task->project === null) {
            return false;
        }

        return $this->isProjectManager($user, $task)
            || $task->project
                ->members()
                ->where('user_id', $user->id)
                ->exists();
    }

    private function isProjectManager(
        User $user,
        Task $task
    ): bool {
        return $task->project !== null
            && (int) $task->project->manager_id === (int) $user->id;
    }

    private function hasScope(
        array $scopes,
        PermissionScope $scope
    ): bool {
        return in_array($scope->value, $scopes, true);
    }
}
