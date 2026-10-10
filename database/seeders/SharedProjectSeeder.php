<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Projects\Models\Project;
use Illuminate\Database\Seeder;

class SharedProjectSeeder extends Seeder
{
    public function run(): void
    {
        $exists = Project::query()
            ->where('is_system_default', true)
            ->whereNull('department_id')
            ->exists();

        if ($exists) {
            return;
        }

        Project::query()->create([
            'department_id' => null,
            'name' => 'پروژه عمومی سازمان',
            'description' => 'پروژه عمومی مشترک سازمان برای کارهای بین‌واحدی',
            'status' => 'active',
            'start_date' => null,
            'end_date' => null,
            'manager_id' => null,
            'created_by' => null,
            'is_active' => true,
            'is_system_default' => true,
        ]);
    }
}
