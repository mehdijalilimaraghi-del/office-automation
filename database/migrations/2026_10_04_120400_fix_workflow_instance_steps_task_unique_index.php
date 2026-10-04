<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_instance_steps', function (Blueprint $table): void {
            $table->dropUnique('workflow_instance_steps_task_id_unique');
        });

        if (DB::getDriverName() === 'sqlsrv') {
            DB::statement(
                'CREATE UNIQUE INDEX workflow_instance_steps_task_id_unique ON workflow_instance_steps (task_id) WHERE task_id IS NOT NULL'
            );
        } else {
            Schema::table('workflow_instance_steps', function (Blueprint $table): void {
                $table->unique('task_id');
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlsrv') {
            DB::statement('DROP INDEX workflow_instance_steps_task_id_unique ON workflow_instance_steps');
        } else {
            Schema::table('workflow_instance_steps', function (Blueprint $table): void {
                $table->dropUnique('workflow_instance_steps_task_id_unique');
            });
        }

        Schema::table('workflow_instance_steps', function (Blueprint $table): void {
            $table->unique('task_id');
        });
    }
};
