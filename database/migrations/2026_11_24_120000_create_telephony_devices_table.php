<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telephony_devices', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('computer_name', 120)->nullable();
            $table->string('status', 24)->default('awaiting_pairing');
            $table->string('pairing_hash', 64)->nullable()->index();
            $table->dateTime('pairing_expires_at')->nullable();
            $table->string('token_hash', 64)->nullable()->index();
            $table->dateTime('paired_at')->nullable();
            $table->dateTime('last_seen_at')->nullable();
            $table->string('runtime_state', 24)->nullable();
            $table->string('applied_revision', 64)->nullable();
            $table->string('error_code', 80)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telephony_devices');
    }
};
