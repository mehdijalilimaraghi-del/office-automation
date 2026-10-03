<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('external_departments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('external_organization_id')->constrained('external_organizations')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('external_departments')->noActionOnDelete();
            $table->string('name');
            $table->string('code')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('extension', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->unique(['external_organization_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_departments');
    }
};