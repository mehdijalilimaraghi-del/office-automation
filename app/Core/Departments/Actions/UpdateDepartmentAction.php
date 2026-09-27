<?php

namespace App\Core\Departments\Actions;

use App\Core\Departments\Models\Department;
use Illuminate\Validation\ValidationException;

class UpdateDepartmentAction
{
    public function execute(Department $department, array $data): Department
    {
        $parentId = $data['parent_id'] ?? null;

        if ($parentId !== null) {
            if ((int) $parentId === $department->id) {
                throw ValidationException::withMessages([
                    'parent_id' => 'یک دپارتمان نمی‌تواند والد خودش باشد.',
                ]);
            }

            $descendantIds = $department->descendantIds();

            if (in_array((int) $parentId, $descendantIds, true)) {
                throw ValidationException::withMessages([
                    'parent_id' => 'یک دپارتمان نمی‌تواند زیرمجموعه یکی از فرزندان خودش باشد.',
                ]);
            }
        }

        $department->update([
            'parent_id' => $parentId,
            'name' => $data['name'],
            'code' => $data['code'] ?? null,
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);

        return $department->refresh();
    }
}