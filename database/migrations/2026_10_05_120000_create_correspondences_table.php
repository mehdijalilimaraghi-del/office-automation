<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('correspondences', function (Blueprint $table): void {
            $table->id();
            $table->string('registration_number', 50)->unique();
            $table->string('type', 20);
            $table->string('status', 20)->default('draft');
            $table->date('registered_at')->nullable();
            $table->string('subject', 500);
            $table->text('body')->nullable();
            $table->foreignId('sender_organization_id')->nullable()->constrained('external_organizations')->nullOnDelete();
            $table->foreignId('sender_department_id')->nullable()->constrained('external_departments')->nullOnDelete();
            $table->foreignId('sender_contact_id')->nullable()->constrained('external_contacts')->nullOnDelete();
            $table->foreignId('recipient_organization_id')->nullable()->constrained('external_organizations')->nullOnDelete();
            $table->foreignId('recipient_department_id')->nullable()->constrained('external_departments')->nullOnDelete();
            $table->foreignId('recipient_contact_id')->nullable()->constrained('external_contacts')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['type', 'status']);
            $table->index('registered_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('correspondences');
    }
};