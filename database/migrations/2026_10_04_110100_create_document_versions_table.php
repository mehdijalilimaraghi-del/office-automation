<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('document_id')->constrained('documents');
            $table->unsignedInteger('version_number');
            $table->string('disk', 50);
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 255)->nullable();
            $table->unsignedBigInteger('size');
            $table->string('checksum', 64);
            $table->foreignId('uploaded_by')->constrained('users');
            $table->timestamps();

            $table->unique(['document_id', 'version_number']);
            $table->index(['document_id', 'version_number']);
            $table->index('checksum');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_versions');
    }
};