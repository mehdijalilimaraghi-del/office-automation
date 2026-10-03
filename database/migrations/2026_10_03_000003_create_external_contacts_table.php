<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('external_contacts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('external_organization_id')->constrained('external_organizations')->cascadeOnDelete();
            $table->foreignId('external_department_id')->nullable()->constrained('external_departments')->nullOnDelete();
            $table->string('name');
            $table->string('position')->nullable();
            $table->string('mobile', 50)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('extension', 20)->nullable();
            $table->string('email')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_contacts');
    }
};