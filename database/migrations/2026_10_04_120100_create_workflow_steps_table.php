<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_steps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workflow_definition_id')->constrained('workflow_definitions');
            $table->unsignedInteger('step_order');
            $table->string('name');
            $table->string('task_title');
            $table->text('task_description')->nullable();
            $table->string('actor_type', 50);
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->foreignId('department_id')->nullable()->constrained('departments');
            $table->boolean('review_required')->default(false);
            $table->string('priority', 20)->default('normal');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['workflow_definition_id', 'step_order']);
            $table->index(['actor_type', 'actor_id']);
            $table->index(['workflow_definition_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_steps');
    }
};