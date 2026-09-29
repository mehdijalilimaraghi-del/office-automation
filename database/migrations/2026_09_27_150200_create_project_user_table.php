<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_user', function (Blueprint $table) {
            $table->id();

            $table->foreignId('project_id');
            $table->foreignId('user_id');
            $table->foreignId('project_role_id');

            $table->timestamps();

            $table->unique([
                'project_id',
                'user_id',
            ]);

            $table->index('user_id');
            $table->index('project_role_id');

            $table->foreign('project_id')
                ->references('id')
                ->on('projects')
                ->cascadeOnDelete();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();

            $table->foreign('project_role_id')
    		->references('id')
    		->on('project_roles');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_user');
    }
};