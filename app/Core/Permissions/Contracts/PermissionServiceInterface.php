<?php

namespace App\Core\Permissions\Contracts;

use App\Core\Permissions\Models\Permission;
use Illuminate\Database\Eloquent\Collection;

interface PermissionServiceInterface
{
    public function create(array $data): Permission;

    public function update(
        Permission $permission,
        array $data
    ): Permission;

    public function delete(Permission $permission): void;

    public function toggleStatus(Permission $permission): Permission;

    public function list(): Collection;
}