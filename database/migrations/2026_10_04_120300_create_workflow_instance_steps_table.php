<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_instance_steps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workflow_instance_id')->constrained('workflow_instances');
            $table->foreignId('workflow_step_id')->constrained('workflow_steps');
            $table->foreignId('task_id')->nullable()->constrained('tasks');
            $table->string('status', 30)->default('pending');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['workflow_instance_id', 'workflow_step_id']);
            // Added below as a filtered unique index on SQL Server.
            $table->index(['workflow_instance_id', 'status']);
        });

        if (DB::getDriverName() === 'sqlsrv') {
            DB::statement('CREATE UNIQUE INDEX workflow_instance_steps_task_id_unique ON workflow_instance_steps (task_id) WHERE task_id IS NOT NULL');
        } else {
            Schema::table('workflow_instance_steps', function (Blueprint $table): void {
                $table->unique('task_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_instance_steps');
    }
};