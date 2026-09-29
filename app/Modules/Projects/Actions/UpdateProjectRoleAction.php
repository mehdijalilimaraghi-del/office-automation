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
        $code = trim((string) ($data['code'] ?? $role->code));

        if ($name === '') {
            throw new InvalidArgumentException(
                'نام نقش پروژه الزامی است.'
            );
        }

        if ($code === '') {
            throw new InvalidArgumentException(
                'کد نقش پروژه الزامی است.'
            );
        }

        $exists = ProjectRole::query()
            ->where('project_id', $role->project_id)
            ->where('code', $code)
            ->whereKeyNot($role->id)
            ->exists();

        if ($exists) {
            throw new InvalidArgumentException(
                'این کد نقش قبلاً در این پروژه استفاده شده است.'
            );
        }

        $role->update([
            'name' => $name,
            'code' => $code,
            'description' => $data['description'] ?? $role->description,
        ]);

        return $role->refresh();
    }
}