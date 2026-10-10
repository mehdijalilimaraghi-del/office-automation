<?php

declare(strict_types=1);

namespace App\Modules\Projects\Actions;

use App\Core\Departments\Models\Department;
use App\Modules\Projects\Models\Project;
use Illuminate\Support\Facades\DB;

class CreateDepartmentDefaultProjectAction
{
    public function execute(Department $department): Project
    {
        return DB::transaction(function (): Project {
            $project = Project::query()
                ->where('is_system_default', true)
                ->whereNull('department_id')
                ->first();

            if ($project === null) {
                $project = Project::query()->create([
                    'department_id' => null,
                    'name' => 'پروژه عمومی سازمان',
                    'description' => 'پروژه عمومی مشترک سازمان برای کارهای بین‌واحدی',
                    'status' => 'active',
                    'manager_id' => null,
                    'created_by' => null,
                    'is_active' => true,
                    'is_system_default' => true,
                ]);
            }

            return $project->load(['manager', 'creator', 'members']);
        });
    }
}
