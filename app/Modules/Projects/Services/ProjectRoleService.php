<?php

namespace App\Modules\Projects\Services;

use App\Modules\Projects\Actions\CreateProjectRoleAction;
use App\Modules\Projects\Actions\DeleteProjectRoleAction;
use App\Modules\Projects\Actions\ToggleProjectRoleStatusAction;
use App\Modules\Projects\Actions\UpdateProjectRoleAction;
use App\Modules\Projects\Contracts\ProjectRoleServiceContract;
use App\Modules\Projects\Models\ProjectRole;

class ProjectRoleService implements ProjectRoleServiceContract
{
    public function __construct(
        private readonly CreateProjectRoleAction $createAction,
        private readonly UpdateProjectRoleAction $updateAction,
        private readonly ToggleProjectRoleStatusAction $toggleStatusAction,
        private readonly DeleteProjectRoleAction $deleteAction,
    ) {
    }

    public function create(
        int $projectId,
        array $data
    ): ProjectRole {
        return $this->createAction->execute(
            projectId: $projectId,
            data: $data
        );
    }

    public function update(
        ProjectRole $role,
        array $data
    ): ProjectRole {
        return $this->updateAction->execute(
            role: $role,
            data: $data
        );
    }

    public function toggleStatus(
        ProjectRole $role
    ): ProjectRole {
        return $this->toggleStatusAction->execute(
            role: $role
        );
    }

    public function delete(
        ProjectRole $role
    ): void {
        $this->deleteAction->execute(
            role: $role
        );
    }
}