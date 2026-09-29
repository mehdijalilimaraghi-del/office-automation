<?php

namespace App\Modules\Projects\Actions;

use App\Modules\Projects\Models\ProjectRole;
use InvalidArgumentException;

class ToggleProjectRoleStatusAction
{
    public function execute(
        ProjectRole $role
    ): ProjectRole {
        $role->loadMissing('project');

        if (! $role->project->is_active) {
            throw new InvalidArgumentException(
                'امکان تغییر وضعیت نقش‌های پروژه غیرفعال وجود ندارد.'
            );
        }

        $role->update([
            'is_active' => ! $role->is_active,
        ]);

        return $role->refresh();
    }
}