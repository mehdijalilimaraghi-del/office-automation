<?php

namespace App\Core\Users\Services;

use App\Core\Users\Actions\CreateUserAction;
use App\Core\Users\Actions\DeleteUserAction;
use App\Core\Users\Actions\SyncUserDepartmentsAction;
use App\Core\Users\Actions\SyncUserRolesAction;
use App\Core\Users\Actions\ToggleUserStatusAction;
use App\Core\Users\Actions\UpdateUserAction;
use App\Core\Users\Contracts\UserServiceInterface;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class UserService implements UserServiceInterface
{
    public function __construct(
        private readonly CreateUserAction $createUserAction,
        private readonly UpdateUserAction $updateUserAction,
        private readonly DeleteUserAction $deleteUserAction,
        private readonly ToggleUserStatusAction $toggleUserStatusAction,
        private readonly SyncUserDepartmentsAction $syncUserDepartmentsAction,
        private readonly SyncUserRolesAction $syncUserRolesAction,
    ) {
    }

    public function create(array $data): User
    {
        return DB::transaction(function () use ($data): User {
            $user = $this->createUserAction->execute($data);

            $this->syncUserDepartmentsAction->execute(
                $user,
                $data['department_ids'] ?? [],
                isset($data['primary_department_id'])
                    ? (int) $data['primary_department_id']
                    : null
            );

            $this->syncUserRolesAction->execute(
                $user,
                $data['role_ids'] ?? []
            );

            return $user->load([
                'departments',
                'roles',
                'profile',
                'positions.department:id,name',
            ]);
        });
    }

    public function update(
        User $user,
        array $data
    ): User {
        return DB::transaction(function () use ($user, $data): User {
            $user = $this->updateUserAction->execute(
                $user,
                $data
            );

            $this->syncUserDepartmentsAction->execute(
                $user,
                $data['department_ids'] ?? [],
                isset($data['primary_department_id'])
                    ? (int) $data['primary_department_id']
                    : null
            );

            $this->syncUserRolesAction->execute(
                $user,
                $data['role_ids'] ?? []
            );

            return $user->load([
                'departments',
                'roles',
                'profile',
                'positions.department:id,name',
            ]);
        });
    }

    public function delete(User $user): void
    {
        $this->deleteUserAction->execute($user);
    }

    public function toggleStatus(User $user): User
    {
        return $this->toggleUserStatusAction->execute($user);
    }

    public function syncDepartments(
        User $user,
        array $departmentIds,
        ?int $primaryDepartmentId = null
    ): User {
        $this->syncUserDepartmentsAction->execute($user, $departmentIds, $primaryDepartmentId);

        return $user->load('departments');
    }

    public function syncRoles(
        User $user,
        array $roleIds
    ): User {
        return $this->syncUserRolesAction->execute(
            $user,
            $roleIds
        );
    }

    public function list(): Collection
    {
        return User::query()
            ->with([
                'departments',
                'roles',
                'profile',
                'positions.department:id,name',
            ])
            ->orderBy('name')
            ->get();
    }

    public function activeList(): Collection
    {
        return User::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
