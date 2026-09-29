<?php

namespace App\Modules\Projects\Contracts;

use App\Modules\Projects\Models\ProjectMember;

interface ProjectMemberServiceContract
{
    public function add(
        int $projectId,
        int $userId,
        ?int $projectRoleId = null
    ): ProjectMember;

    public function changeRole(
        ProjectMember $member,
        ?int $projectRoleId
    ): ProjectMember;

    public function remove(
        ProjectMember $member
    ): void;
}