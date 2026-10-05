<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('correspondence_referrals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('correspondence_id')->constrained('correspondences')->cascadeOnDelete();
            $table->foreignId('from_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('to_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('referred_by')->constrained('users')->restrictOnDelete();
            $table->text('instruction')->nullable();
            $table->timestamp('referred_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['to_user_id', 'completed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('correspondence_referrals');
    }
};