<?php

namespace App\Core\Users\Services;

use App\Core\Users\Actions\CreateUserAction;
use App\Core\Users\Actions\DeleteUserAction;
use App\Core\Users\Actions\SyncUserRolesAction;
use App\Core\Users\Actions\ToggleUserStatusAction;
use App\Core\Users\Actions\UpdateUserAction;
use App\Core\Users\Contracts\UserServiceInterface;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class UserService implements UserServiceInterface
{
    public function __construct(
        private readonly CreateUserAction $createUserAction,
        private readonly UpdateUserAction $updateUserAction,
        private readonly DeleteUserAction $deleteUserAction,
        private readonly ToggleUserStatusAction $toggleUserStatusAction,
        private readonly SyncUserRolesAction $syncUserRolesAction,
    ) {
    }

    public function create(array $data): User
    {
        $user = $this->createUserAction->execute($data);

        $this->syncUserRolesAction->execute(
            $user,
            $data['role_ids'] ?? []
        );

        return $user->load([
            'department',
            'roles',
        ]);
    }

    public function update(
        User $user,
        array $data
    ): User {
        $user = $this->updateUserAction->execute(
            $user,
            $data
        );

        $this->syncUserRolesAction->execute(
            $user,
            $data['role_ids'] ?? []
        );

        return $user->load([
            'department',
            'roles',
        ]);
    }

    public function delete(User $user): void
    {
        $this->deleteUserAction->execute($user);
    }

    public function toggleStatus(User $user): User
    {
        return $this->toggleUserStatusAction->execute($user);
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
                'department',
                'roles',
            ])
            ->orderBy('name')
            ->get();
    }
}