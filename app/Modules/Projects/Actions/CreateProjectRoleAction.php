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
        if ($name === '') {
            throw new InvalidArgumentException(
                'نام نقش پروژه الزامی است.'
            );
        }
        return ProjectRole::query()->create([
            'project_id' => $project->id,
            'name' => $name,
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }
}