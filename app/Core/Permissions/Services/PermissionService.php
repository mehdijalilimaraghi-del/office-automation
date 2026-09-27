<?php

namespace App\Core\Permissions\Services;

use App\Core\Permissions\Actions\CreatePermissionAction;
use App\Core\Permissions\Actions\DeletePermissionAction;
use App\Core\Permissions\Actions\TogglePermissionStatusAction;
use App\Core\Permissions\Actions\UpdatePermissionAction;
use App\Core\Permissions\Contracts\PermissionServiceInterface;
use App\Core\Permissions\Models\Permission;
use Illuminate\Database\Eloquent\Collection;

class PermissionService implements PermissionServiceInterface
{
    public function __construct(
        private readonly CreatePermissionAction $createPermissionAction,
        private readonly UpdatePermissionAction $updatePermissionAction,
        private readonly DeletePermissionAction $deletePermissionAction,
        private readonly TogglePermissionStatusAction $togglePermissionStatusAction,
    ) {
    }

    public function create(array $data): Permission
    {
        return $this->createPermissionAction->execute($data);
    }

    public function update(
        Permission $permission,
        array $data
    ): Permission {
        return $this->updatePermissionAction->execute(
            $permission,
            $data
        );
    }

    public function delete(Permission $permission): void
    {
        $this->deletePermissionAction->execute($permission);
    }

    public function toggleStatus(Permission $permission): Permission
    {
        return $this->togglePermissionStatusAction->execute(
            $permission
        );
    }

    public function list(): Collection
    {
        return Permission::query()
            ->orderBy('name')
            ->get();
    }
}