<?php

namespace App\Core\Departments\Actions;

use App\Core\Departments\Models\Department;
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

        $department->delete();
    }
}