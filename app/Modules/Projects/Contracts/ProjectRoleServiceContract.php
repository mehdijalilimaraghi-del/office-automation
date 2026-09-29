<?php

namespace App\Modules\Projects\Contracts;

use App\Modules\Projects\Models\ProjectRole;

interface ProjectRoleServiceContract
{
    public function create(
        int $projectId,
        array $data
    ): ProjectRole;

    public function update(
        ProjectRole $role,
        array $data
    ): ProjectRole;

    public function toggleStatus(
        ProjectRole $role
    ): ProjectRole;

    public function delete(
        ProjectRole $role
    ): void;
}