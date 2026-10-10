<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Core\Departments\Models\Department;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectSection;
use Illuminate\Database\Seeder;

class SharedProjectSeeder extends Seeder
{
    public function run(): void
    {
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
                'start_date' => null,
                'end_date' => null,
                'manager_id' => null,
                'created_by' => null,
                'is_active' => true,
                'is_system_default' => true,
            ]);
        }

        $sortOrder = (int) ProjectSection::query()
            ->where('project_id', $project->id)
            ->max('sort_order');

        foreach (Department::query()->orderBy('id')->get() as $department) {
            $exists = ProjectSection::query()
                ->where('project_id', $project->id)
                ->where('department_id', $department->id)
                ->exists();

            if ($exists) {
                continue;
            }

            $sortOrder++;

            ProjectSection::query()->create([
                'project_id' => $project->id,
                'department_id' => $department->id,
                'name' => $department->name,
                'description' => 'بخش عمومی فعالیت‌های واحد '.$department->name,
                'manager_can_create_tasks' => true,
                'sort_order' => $sortOrder,
            ]);
        }
    }
}
