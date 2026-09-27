<?php

namespace App\Core\Roles\Contracts;

use App\Core\Roles\Models\Role;
use Illuminate\Database\Eloquent\Collection;

interface RoleServiceInterface
{
    public function create(array $data): Role;

    public function update(Role $role, array $data): Role;

    public function delete(Role $role): void;

    public function toggleStatus(Role $role): Role;

    public function list(): Collection;

    public function syncPermissions(
        Role $role,
        array $permissionIds
    ): Role;
}