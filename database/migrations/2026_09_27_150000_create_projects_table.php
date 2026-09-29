<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();

            $table->string('name', 150);
            $table->string('code', 50);

            $table->text('description')->nullable();

            $table->string('status', 20)
                ->default('planning');

            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();

            $table->foreignId('manager_id');
            $table->foreignId('created_by');

            $table->boolean('is_active')
                ->default(true);

            $table->timestamps();

            $table->unique('code');

            $table->index('status');
            $table->index('is_active');
            $table->index('manager_id');
            $table->index('created_by');

            $table->foreign('manager_id')
                ->references('id')
                ->on('users');

            $table->foreign('created_by')
                ->references('id')
                ->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};