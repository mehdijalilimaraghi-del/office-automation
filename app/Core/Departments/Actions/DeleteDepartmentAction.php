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

        if ($department->defaultProject()->exists()) {
            throw ValidationException::withMessages([
                'department' => 'دپارتمان دارای پروژه عمومی و پیش‌فرض است و قابل حذف نیست.',
            ]);
        }

        DB::transaction(function () use ($department): void {
            Task::query()
                ->where('department_id', $department->id)
                ->update(['department_id' => null]);

            $department->delete();
        });
    }
}