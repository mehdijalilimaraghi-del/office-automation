<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telephony_extensions', function (Blueprint $table): void {
            $table->id();
            $table->string('number', 6)->unique();
            $table->string('label', 100);
            $table->string('owner_type', 20);
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->foreignId('department_id')->nullable()->constrained('departments');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telephony_extensions');
    }
};
