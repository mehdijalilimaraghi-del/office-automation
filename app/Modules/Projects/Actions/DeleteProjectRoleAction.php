<?php

namespace App\Modules\Projects\Actions;

use App\Modules\Projects\Models\ProjectRole;
use InvalidArgumentException;

class DeleteProjectRoleAction
{
    public function execute(
        ProjectRole $role
    ): void {
        $role->loadMissing('project');

        if (! $role->project->is_active) {
            throw new InvalidArgumentException(
                'امکان حذف نقش از پروژه غیرفعال وجود ندارد.'
            );
        }

        if ($role->members()->exists()) {
            throw new InvalidArgumentException(
                'این نقش دارای عضو است و تا زمانی که اعضا از آن جدا نشوند قابل حذف نیست.'
            );
        }

        $role->delete();
    }
}