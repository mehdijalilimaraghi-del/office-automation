<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telephony_discoveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->string('token_hash', 64)->unique();
            $table->dateTime('expires_at');
            $table->dateTime('completed_at')->nullable();
            $table->unsignedInteger('device_count')->nullable();
            $table->timestamps();
        });
        Schema::create('telephony_modems', function (Blueprint $table): void {
            $table->id();
            $table->string('device_key', 64)->unique();
            $table->string('computer_key', 36);
            $table->string('computer_name', 100);
            $table->string('name', 255);
            $table->string('port', 20)->nullable();
            $table->string('driver_status', 30);
            $table->boolean('voice_advertised')->default(false);
            $table->string('probe_status', 30)->default('not_tested');
            $table->text('evidence')->nullable();
            $table->dateTime('last_seen_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telephony_modems');
        Schema::dropIfExists('telephony_discoveries');
    }
};
