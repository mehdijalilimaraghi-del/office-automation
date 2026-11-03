<?php

namespace App\Core\Departments\Actions;

use App\Core\Departments\Models\Department;
use App\Modules\Tasks\Models\Task;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectSection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteDepartmentAction
{
    public function execute(Department $department): void
    {
        if ($department->is_system_default) {
            throw ValidationException::withMessages([
                'department' => 'واحدهای پیش‌فرض سازمان قابل حذف نیستند.',
            ]);
        }

        if ($department->children()->exists()) {
            throw ValidationException::withMessages([
                'department' => 'این دپارتمان دارای زیرمجموعه است و قابل حذف نیست.',
            ]);
        }

        if ($department->manager_id !== null) {
            throw ValidationException::withMessages([
                'department' => 'دپارتمان دارای مدیر است. ابتدا مدیر دپارتمان را حذف کنید.',
            ]);
        }

        DB::transaction(function () use ($department): void {
            $sharedProject = Project::query()
                ->where('is_system_default', true)
                ->whereNull('department_id')
                ->first();

            if ($sharedProject !== null) {
                $section = ProjectSection::query()
                    ->where('project_id', $sharedProject->id)
                    ->where('department_id', $department->id)
                    ->first();

                if ($section !== null) {
                    if ($section->tasks()->exists() || $section->documents()->exists()) {
                        throw ValidationException::withMessages([
                            'department' => 'این واحد در پروژه عمومی دارای دستورکار یا سند است؛ ابتدا سوابق آن را منتقل یا تعیین تکلیف کنید.',
                        ]);
                    }

                    $section->delete();
                }
            }

            $defaultProject = $department->defaultProject()->first();

            if ($defaultProject !== null) {
                $defaultProject->delete();
            }

            Task::query()
                ->where('department_id', $department->id)
                ->update(['department_id' => null]);

            $department->delete();
        });
    }
}
