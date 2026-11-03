<?php

declare(strict_types=1);

namespace App\Modules\Projects\Actions;

use App\Core\Departments\Models\Department;
use App\Modules\Projects\Models\ProjectSection;
use Illuminate\Support\Facades\DB;

class EnsureDepartmentProjectSectionAction
{
    public function __construct(
        private readonly CreateDepartmentDefaultProjectAction $sharedProjectAction,
    ) {
    }

    public function execute(Department $department): ProjectSection
    {
        return DB::transaction(function () use ($department): ProjectSection {
            $project = $this->sharedProjectAction->execute($department);
            $department->loadMissing('manager');

            $attributes = [
                'name' => $department->name,
                'description' => 'بخش عمومی فعالیت‌های واحد '.$department->name,
                'manager_can_create_tasks' => $department->is_active
                    && $department->manager !== null
                    && $department->manager->isActive(),
            ];

            $section = ProjectSection::query()
                ->where('project_id', $project->id)
                ->where('department_id', $department->id)
                ->first();

            if ($section !== null) {
                $section->update($attributes);

                return $section->refresh();
            }

            return ProjectSection::query()->create([
                ...$attributes,
                'project_id' => $project->id,
                'department_id' => $department->id,
                'sort_order' => (int) ProjectSection::query()
                    ->where('project_id', $project->id)
                    ->max('sort_order') + 1,
            ]);
        });
    }
}
