<?php

namespace App\Modules\Projects\Actions;

use App\Models\User;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectMember;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CreateProjectAction
{
    public function execute(
        array $data,
        User $creator
    ): Project {
        return DB::transaction(
            function () use ($data, $creator): Project {
                $manager = User::query()->find(
                    $data['manager_id']
                );

                if ($manager === null) {
                    throw new InvalidArgumentException(
                        'مدیر پروژه یافت نشد.'
                    );
                }

                if (! $manager->isActive()) {
                    throw new InvalidArgumentException(
                        'مدیر پروژه باید کاربر فعال باشد.'
                    );
                }

                if (
                    isset($data['start_date']) &&
                    isset($data['end_date']) &&
                    $data['start_date'] !== null &&
                    $data['end_date'] !== null &&
                    $data['end_date'] < $data['start_date']
                ) {
                    throw new InvalidArgumentException(
                        'تاریخ پایان پروژه نمی‌تواند قبل از تاریخ شروع باشد.'
                    );
                }

                $project = Project::query()->create([
                    'department_id' => $data['department_id'] ?? null,
                    'name' => $data['name'],
                    'description' => $data['description'] ?? null,
                    'status' => $data['status'] ?? 'planning',
                    'start_date' => $data['start_date'] ?? null,
                    'end_date' => $data['end_date'] ?? null,
                    'manager_id' => $manager->id,
                    'created_by' => $creator->id,
                    'is_active' => $data['is_active'] ?? true,
                ]);

                ProjectMember::query()->create([
                    'project_id' => $project->id,
                    'user_id' => $manager->id,
                    'project_role_id' => null,
                ]);

                return $project->load([
                    'manager',
                    'creator',
                    'department',
                    'members',
                ]);
            }
        );
    }
}