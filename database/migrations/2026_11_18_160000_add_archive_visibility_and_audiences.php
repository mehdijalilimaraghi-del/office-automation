<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table): void {
            $table->string('archive_visibility', 20)->nullable();
        });

        Schema::create('archive_audiences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->string('target_type', 20);
            $table->unsignedBigInteger('target_id');
            $table->unique(['document_id', 'target_type', 'target_id'], 'archive_audience_unique');
            $table->index(['target_type', 'target_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('archive_audiences');
        Schema::table('documents', function (Blueprint $table): void {
            $table->dropColumn('archive_visibility');
        });
    }
};
