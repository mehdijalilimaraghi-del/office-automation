<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('archive_custody_events', function (Blueprint $table): void {
            $table->foreignId('recipient_user_id')->nullable()->constrained('users');
        });
        Schema::create('reminder_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('delivery_key', 64);
            $table->timestamp('sent_at');
            $table->unique(['user_id', 'delivery_key'], 'reminder_delivery_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminder_deliveries');
        Schema::table('archive_custody_events', function (Blueprint $table): void {
            $table->dropForeign(['recipient_user_id']);
            $table->dropColumn('recipient_user_id');
        });
    }
};
