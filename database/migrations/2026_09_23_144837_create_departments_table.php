<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('parent_id')
                ->nullable();

            $table->string('name', 150);
            $table->string('code', 50)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index('parent_id');
            $table->index('code');

            $table->foreign('parent_id')
                ->references('id')
                ->on('departments');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};