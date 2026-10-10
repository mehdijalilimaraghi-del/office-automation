<?php

namespace App\Core\Departments\Actions;

use App\Core\Departments\Models\Department;
use Illuminate\Validation\ValidationException;

class ToggleDepartmentStatusAction
{
    public function execute(Department $department): Department
    {
        if ($department->is_system_default) {
            throw ValidationException::withMessages([
                'department' => 'وضعیت واحدهای پیش‌فرض قابل تغییر نیست.',
            ]);
        }

        $department->update([
            'is_active' => ! $department->is_active,
        ]);

        return $department->refresh();
    }
}
