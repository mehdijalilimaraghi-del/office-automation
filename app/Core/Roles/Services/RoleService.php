<?php

namespace App\Core\Roles\Services;

use App\Core\Roles\Actions\CreateRoleAction;
use App\Core\Roles\Actions\DeleteRoleAction;
use App\Core\Roles\Actions\SyncRolePermissionsAction;
use App\Core\Roles\Actions\ToggleRoleStatusAction;
use App\Core\Roles\Actions\UpdateRoleAction;
use App\Core\Roles\Contracts\RoleServiceInterface;
use App\Core\Roles\Models\Role;
use Illuminate\Database\Eloquent\Collection;

class RoleService implements RoleServiceInterface
{
    public function __construct(
        private readonly CreateRoleAction $createRoleAction,
        private readonly UpdateRoleAction $updateRoleAction,
        private readonly DeleteRoleAction $deleteRoleAction,
        private readonly ToggleRoleStatusAction $toggleRoleStatusAction,
        private readonly SyncRolePermissionsAction $syncRolePermissionsAction,
    ) {
    }

    public function create(array $data): Role
    {
        return $this->createRoleAction->execute($data);
    }

    public function update(
        Role $role,
        array $data
    ): Role {
        return $this->updateRoleAction->execute(
            $role,
            $data
        );
    }

    public function delete(Role $role): void
    {
        $this->deleteRoleAction->execute($role);
    }

    public function toggleStatus(Role $role): Role
    {
        return $this->toggleRoleStatusAction->execute($role);
    }

    public function list(): Collection
    {
        return Role::query()
            ->with('customFieldValues.field')
            ->orderBy('name')
            ->get();
    }

    public function syncPermissions(
        Role $role,
        array $permissions
    ): Role {
        return $this->syncRolePermissionsAction->execute(
            $role,
            $permissions
        );
    }
}
