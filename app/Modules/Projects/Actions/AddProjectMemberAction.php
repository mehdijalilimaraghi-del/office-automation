<?php

namespace App\Modules\Projects\Actions;

use App\Models\User;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectMember;
use App\Modules\Projects\Models\ProjectRole;
use InvalidArgumentException;

class AddProjectMemberAction
{
    public function execute(
        int $projectId,
        int $userId,
        ?int $projectRoleId = null
    ): ProjectMember {
        $project = Project::query()->find($projectId);

        if ($project === null) {
            throw new InvalidArgumentException(
                'پروژه یافت نشد.'
            );
        }

        if (! $project->is_active) {
            throw new InvalidArgumentException(
                'امکان افزودن عضو به پروژه غیرفعال وجود ندارد.'
            );
        }

        $user = User::query()->find($userId);

        if ($user === null) {
            throw new InvalidArgumentException(
                'کاربر یافت نشد.'
            );
        }

        if (! $user->isActive()) {
            throw new InvalidArgumentException(
                'فقط کاربران فعال می‌توانند عضو پروژه شوند.'
            );
        }

        $alreadyMember = ProjectMember::query()
            ->where('project_id', $project->id)
            ->where('user_id', $user->id)
            ->exists();

        if ($alreadyMember) {
            throw new InvalidArgumentException(
                'این کاربر قبلاً عضو پروژه است.'
            );
        }

        if ($projectRoleId !== null) {
            $role = ProjectRole::query()
                ->whereKey($projectRoleId)
                ->where('project_id', $project->id)
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

        return ProjectMember::query()->create([
            'project_id' => $project->id,
            'user_id' => $user->id,
            'project_role_id' => $projectRoleId,
        ]);
    }
}