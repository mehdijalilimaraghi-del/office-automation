<?php

namespace App\Core\Departments\Actions;

use App\Core\Departments\Models\Department;

class ToggleDepartmentStatusAction
{
    public function execute(Department $department): Department
    {
        $department->update([
            'is_active' => ! $department->is_active,
        ]);

        return $department->refresh();
    }
}