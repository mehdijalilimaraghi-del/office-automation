<?php

namespace App\Modules\Projects\Contracts;

use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectMember;
use Illuminate\Support\Collection;

interface ProjectMemberServiceContract
{
    public function availableUsers(Project $project): Collection;

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