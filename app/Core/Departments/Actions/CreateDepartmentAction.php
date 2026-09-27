<?php

namespace App\Core\Departments\Actions;

use App\Core\Departments\Models\Department;

class CreateDepartmentAction
{
    public function execute(array $data): Department
    {
        return Department::create([
            'parent_id' => $data['parent_id'] ?? null,
            'name' => $data['name'],
            'code' => $data['code'] ?? null,
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }
}