<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('archive_references', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->string('target_type', 20);
            $table->unsignedBigInteger('target_id');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->unique(['document_id', 'target_type', 'target_id'], 'archive_reference_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('archive_references');
    }
};
