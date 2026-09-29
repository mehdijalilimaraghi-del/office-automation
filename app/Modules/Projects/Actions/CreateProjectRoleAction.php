<?php

namespace App\Modules\Projects\Actions;

use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectRole;
use InvalidArgumentException;

class CreateProjectRoleAction
{
    public function execute(
        int $projectId,
        array $data
    ): ProjectRole {
        $project = Project::query()->find($projectId);

        if ($project === null) {
            throw new InvalidArgumentException(
                'پروژه یافت نشد.'
            );
        }

        if (! $project->is_active) {
            throw new InvalidArgumentException(
                'امکان مدیریت نقش‌های پروژه غیرفعال وجود ندارد.'
            );
        }

        $name = trim((string) ($data['name'] ?? ''));
        $code = trim((string) ($data['code'] ?? ''));

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
            ->where('project_id', $project->id)
            ->where('code', $code)
            ->exists();

        if ($exists) {
            throw new InvalidArgumentException(
                'این کد نقش قبلاً در این پروژه استفاده شده است.'
            );
        }

        return ProjectRole::query()->create([
            'project_id' => $project->id,
            'name' => $name,
            'code' => $code,
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }
}