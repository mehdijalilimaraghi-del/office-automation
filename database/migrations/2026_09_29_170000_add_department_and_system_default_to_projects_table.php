<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('department_id')
                ->nullable()
                ->after('id');

            $table->boolean('is_system_default')
                ->default(false)
                ->after('is_active');

            $table->index('department_id');
            $table->index('is_system_default');

            $table->foreign('department_id')
                ->references('id')
                ->on('departments')
                ->cascadeOnDelete();
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('manager_id')->nullable()->change();
            $table->foreignId('created_by')->nullable()->change();
        });

        DB::statement(
            "CREATE UNIQUE INDEX projects_default_department_unique
             ON projects (department_id)
             WHERE department_id IS NOT NULL AND is_system_default = 1"
        );
    }

    public function down(): void
    {
        DB::statement(
            'DROP INDEX projects_default_department_unique ON projects'
        );

        Schema::table('projects', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropIndex(['department_id']);
            $table->dropIndex(['is_system_default']);
            $table->dropColumn(['department_id', 'is_system_default']);
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('manager_id')->nullable(false)->change();
            $table->foreignId('created_by')->nullable(false)->change();
        });
    }
};
