<?php

namespace App\Core\Departments\Actions;

use App\Core\Departments\Models\Department;
use App\Modules\Projects\Actions\EnsureDepartmentProjectSectionAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ToggleDepartmentStatusAction
{
    public function __construct(
        private readonly EnsureDepartmentProjectSectionAction $ensureDepartmentProjectSectionAction,
    ) {
    }

    public function execute(Department $department): Department
    {
        if ($department->is_system_default) {
            throw ValidationException::withMessages([
                'department' => 'وضعیت واحدهای پیش‌فرض قابل تغییر نیست.',
            ]);
        }

        return DB::transaction(function () use ($department): Department {
            $department->update([
                'is_active' => ! $department->is_active,
            ]);

            $this->ensureDepartmentProjectSectionAction->execute($department->refresh());

            return $department->refresh();
        });
    }
}
