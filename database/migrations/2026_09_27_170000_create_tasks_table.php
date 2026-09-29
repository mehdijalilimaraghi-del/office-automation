<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();

            $table->foreignId('project_id');

            $table->string('title', 200);
            $table->text('description')->nullable();

            $table->string('status', 30)
                ->default('todo');

            $table->string('priority', 20)
                ->default('normal');

            $table->foreignId('assignee_id');
            $table->foreignId('created_by');

            $table->date('start_date')->nullable();
            $table->date('due_date')->nullable();

            $table->timestamp('completed_at')->nullable();

            $table->boolean('is_active')
                ->default(true);

            $table->timestamps();

            $table->index('project_id');
            $table->index('assignee_id');
            $table->index('created_by');
            $table->index('status');
            $table->index('priority');
            $table->index('due_date');
            $table->index('is_active');

            $table->foreign('project_id')
                ->references('id')
                ->on('projects')
                ->cascadeOnDelete();

            $table->foreign('assignee_id')
                ->references('id')
                ->on('users');

            $table->foreign('created_by')
                ->references('id')
                ->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};