<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_instances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workflow_definition_id')->constrained('workflow_definitions');
            $table->foreignId('initiated_by')->constrained('users');
            $table->foreignId('project_id')->nullable()->constrained('projects');
            $table->foreignId('department_id')->nullable()->constrained('departments');
            $table->string('status', 30)->default('running');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'started_at']);
            $table->index(['project_id', 'status']);
            $table->index(['department_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_instances');
    }
};