<?php

namespace App\Modules\Projects\Actions;

use App\Modules\Projects\Models\ProjectRole;
use InvalidArgumentException;

class UpdateProjectRoleAction
{
    public function execute(
        ProjectRole $role,
        array $data
    ): ProjectRole {
        $role->loadMissing('project');

        if (! $role->project->is_active) {
            throw new InvalidArgumentException(
                'امکان ویرایش نقش‌های پروژه غیرفعال وجود ندارد.'
            );
        }

        $name = trim((string) ($data['name'] ?? $role->name));
        if ($name === '') {
            throw new InvalidArgumentException(
                'نام نقش پروژه الزامی است.'
            );
        }
        $role->update([
            'name' => $name,
            'description' => $data['description'] ?? $role->description,
        ]);

        return $role->refresh();
    }
}