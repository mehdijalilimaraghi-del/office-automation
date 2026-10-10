<?php

namespace Database\Seeders;

use App\Core\Departments\Models\Department;
use Illuminate\Database\Seeder;

class DefaultDepartmentSeeder extends Seeder
{
    public function run(): void
    {
        $management = Department::query()->firstOrNew([
            'name' => 'واحد مدیریت',
        ]);
        $management->parent_id = null;
        $management->icon = $management->icon ?: 'building-2';
        $management->description = $management->description ?: 'واحد اصلی مدیریت سازمان';
        $management->is_active = true;
        $management->is_system_default = true;
        $management->save();

        $projectManagement = Department::query()->firstOrNew([
            'name' => 'مدیریت پروژه',
        ]);
        $projectManagement->parent_id = $management->id;
        $projectManagement->icon = $projectManagement->icon ?: 'briefcase';
        $projectManagement->description = $projectManagement->description ?: 'واحد مدیریت پروژه';
        $projectManagement->is_active = true;
        $projectManagement->is_system_default = true;
        $projectManagement->save();
    }
}
