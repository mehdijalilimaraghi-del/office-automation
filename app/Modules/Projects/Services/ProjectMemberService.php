<?php

namespace App\Modules\Projects\Services;

use App\Modules\Projects\Actions\AddProjectMemberAction;
use App\Modules\Projects\Actions\ChangeProjectMemberRoleAction;
use App\Modules\Projects\Actions\RemoveProjectMemberAction;
use App\Modules\Projects\Contracts\ProjectMemberServiceContract;
use App\Modules\Projects\Models\ProjectMember;

class ProjectMemberService implements ProjectMemberServiceContract
{
    public function __construct(
        private readonly AddProjectMemberAction $addAction,
        private readonly ChangeProjectMemberRoleAction $changeRoleAction,
        private readonly RemoveProjectMemberAction $removeAction,
    ) {
    }

    public function add(
        int $projectId,
        int $userId,
        ?int $projectRoleId = null
    ): ProjectMember {
        return $this->addAction->execute(
            projectId: $projectId,
            userId: $userId,
            projectRoleId: $projectRoleId
        );
    }

    public function changeRole(
        ProjectMember $member,
        ?int $projectRoleId
    ): ProjectMember {
        return $this->changeRoleAction->execute(
            member: $member,
            projectRoleId: $projectRoleId
        );
    }

    public function remove(
        ProjectMember $member
    ): void {
        $this->removeAction->execute(
            member: $member
        );
    }
}