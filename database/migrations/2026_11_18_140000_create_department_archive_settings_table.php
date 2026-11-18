<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('department_archive_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('department_id')->unique()->constrained('departments');
            $table->foreignId('archivist_id')->nullable()->constrained('users');
            $table->text('categories')->nullable();
            $table->text('locations')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('department_archive_settings');
    }
};
