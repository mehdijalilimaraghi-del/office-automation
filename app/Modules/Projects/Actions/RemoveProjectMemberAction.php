<?php

namespace App\Modules\Projects\Actions;

use App\Modules\Projects\Models\ProjectMember;
use InvalidArgumentException;

class RemoveProjectMemberAction
{
    public function execute(
        ProjectMember $member
    ): void {
        $member->loadMissing('project');

        if (! $member->project->is_active) {
            throw new InvalidArgumentException(
                'امکان حذف عضو از پروژه غیرفعال وجود ندارد.'
            );
        }

        if ($member->user_id === $member->project->manager_id) {
            throw new InvalidArgumentException(
                'مدیر پروژه را نمی‌توان از اعضای پروژه حذف کرد.'
            );
        }

        $member->delete();
    }
}