<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('correspondences', function (Blueprint $table): void {
            $table->string('classification', 20)->default('ordinary')->after('type');
        });

        Schema::create('correspondence_user_access', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('correspondence_id')->constrained('correspondences')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('granted_by')->nullable();
            $table->string('reason', 100)->default('participant');
            $table->timestamps();
            $table->unique(['correspondence_id', 'user_id']);
            $table->index(['user_id', 'correspondence_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('correspondence_user_access');

        Schema::table('correspondences', function (Blueprint $table): void {
            $table->dropColumn('classification');
        });
    }
};
