<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_accesses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('document_id')->constrained('documents');
            $table->foreignId('user_id')->constrained('users');
            $table->string('access_level', 20)->default('view');
            $table->foreignId('granted_by')->constrained('users');
            $table->timestamps();

            $table->unique(['document_id', 'user_id']);
            $table->index(['user_id', 'access_level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_accesses');
    }
};