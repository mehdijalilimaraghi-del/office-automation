<?php

namespace App\Modules\Projects\Actions;

use App\Modules\Projects\Models\ProjectMember;
use App\Modules\Projects\Models\ProjectRole;
use InvalidArgumentException;

class ChangeProjectMemberRoleAction
{
    public function execute(
        ProjectMember $member,
        ?int $projectRoleId
    ): ProjectMember {
        $member->loadMissing('project');

        if (! $member->project->is_active) {
            throw new InvalidArgumentException(
                'امکان تغییر نقش اعضای پروژه غیرفعال وجود ندارد.'
            );
        }

        if ($projectRoleId !== null) {
            $role = ProjectRole::query()
                ->whereKey($projectRoleId)
                ->where('project_id', $member->project_id)
                ->first();

            if ($role === null) {
                throw new InvalidArgumentException(
                    'نقش انتخاب‌شده متعلق به این پروژه نیست.'
                );
            }

            if (! $role->is_active) {
                throw new InvalidArgumentException(
                    'نقش انتخاب‌شده غیرفعال است.'
                );
            }
        }

        $member->update([
            'project_role_id' => $projectRoleId,
        ]);

        return $member->refresh();
    }
}