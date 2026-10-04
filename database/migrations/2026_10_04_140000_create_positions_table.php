<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('positions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('department_id')->constrained('departments');
            $table->string('name', 150);
            $table->string('code', 50)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['department_id', 'name']);
            $table->index(['department_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('positions');
    }
};