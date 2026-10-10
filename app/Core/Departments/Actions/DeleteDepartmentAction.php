<?php

namespace App\Core\Departments\Actions;

use App\Core\Departments\Models\Department;
use App\Modules\Tasks\Models\Task;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteDepartmentAction
{
    public function execute(Department $department): void
    {
        $isLegacyManagement = $department->code === 'ORG-000001';

        if ($department->is_system_default && ! $isLegacyManagement) {
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
