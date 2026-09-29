<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_roles', function (Blueprint $table) {
            $table->id();

            $table->foreignId('project_id');

            $table->string('name', 100);
            $table->string('code', 50);

            $table->text('description')->nullable();

            $table->boolean('is_active')
                ->default(true);

            $table->timestamps();

            $table->unique([
                'project_id',
                'code',
            ]);

            $table->index('project_id');
            $table->index('is_active');

            $table->foreign('project_id')
                ->references('id')
                ->on('projects')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_roles');
    }
};