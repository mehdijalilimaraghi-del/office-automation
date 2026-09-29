<?php

declare(strict_types=1);

namespace App\Modules\Projects\Actions;

use App\Core\Departments\Models\Department;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectMember;
use Illuminate\Support\Facades\DB;

class CreateDepartmentDefaultProjectAction
{
    public function execute(Department $department): Project
    {
        return DB::transaction(function () use ($department): Project {
            $project = Project::query()->create([
                'department_id' => $department->id,
                'name' => 'پروژه عمومی '.$department->name,
                'code' => 'DEPT-'.$department->id.'-DEFAULT',
                'description' => 'پروژه عمومی و پیش‌فرض دپارتمان',
                'status' => 'active',
                'start_date' => null,
                'end_date' => null,
                'manager_id' => $department->manager_id,
                'created_by' => null,
                'is_active' => true,
                'is_system_default' => true,
            ]);

            if ($department->manager_id !== null) {
                ProjectMember::query()->create([
                    'project_id' => $project->id,
                    'user_id' => $department->manager_id,
                    'project_role_id' => null,
                ]);
            }

            return $project->load([
                'manager',
                'creator',
                'department',
                'members',
            ]);
        });
    }
}
