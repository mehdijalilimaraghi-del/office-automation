<?php

use App\Core\Departments\Models\Department;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('departments', function (Blueprint $table): void {
            $table->boolean('is_system_default')->default(false);
        });

        $management = Department::query()->firstOrNew([
            'name' => 'واحد مدیریت',
        ]);
        $management->parent_id = null;
        $management->manager_id ??= null;
        $management->icon ??= 'building-2';
        $management->description ??= 'واحد اصلی مدیریت سازمان';
        $management->is_active = true;
        $management->is_system_default = true;
        $management->save();

        $projectManagement = Department::query()->firstOrNew([
            'name' => 'مدیریت پروژه',
        ]);
        $projectManagement->parent_id = $management->id;
        $projectManagement->manager_id ??= null;
        $projectManagement->icon ??= 'briefcase';
        $projectManagement->description ??= 'واحد مدیریت پروژه';
        $projectManagement->is_active = true;
        $projectManagement->is_system_default = true;
        $projectManagement->save();
    }

    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table): void {
            $table->dropColumn('is_system_default');
        });
    }
};
