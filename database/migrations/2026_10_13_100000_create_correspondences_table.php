<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('correspondences', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 20);
            $table->string('subject');
            $table->longText('body')->nullable();
            $table->string('status', 20)->default('draft');
            $table->string('registration_number', 100)->nullable();
            $table->timestamp('registered_at')->nullable();
            $table->foreignId('registered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('sender_name')->nullable();
            $table->string('recipient_name')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('workflow_instance_id')->nullable()->constrained('workflow_instances')->nullOnDelete();
            $table->foreignId('reply_to_id')->nullable()->constrained('correspondences')->nullOnDelete();
            $table->timestamps();

            $table->index(['type', 'status', 'created_at']);
            $table->index(['department_id', 'status']);
            $table->index(['created_by', 'status']);
            $table->index('registration_number');
            $table->index('workflow_instance_id');
            $table->index('reply_to_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('correspondences');
    }
};
