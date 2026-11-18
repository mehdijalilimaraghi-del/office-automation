<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('correspondences', function (Blueprint $table): void {
            $table->foreignId('archive_document_id')->nullable()->constrained('documents');
            $table->index('archive_document_id', 'correspondences_archive_document_lookup');
        });
        Schema::create('archive_correspondence_files', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->unsignedBigInteger('source_version_id');
            $table->string('purpose', 50);
            $table->string('disk', 50);
            $table->string('path', 500);
            $table->string('original_name');
            $table->string('mime_type', 255)->nullable();
            $table->unsignedBigInteger('size');
            $table->string('checksum', 64)->nullable();
            $table->unique(['document_id', 'source_version_id'], 'archive_correspondence_file_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('archive_correspondence_files');
        Schema::table('correspondences', function (Blueprint $table): void {
            $table->dropForeign(['archive_document_id']);
            $table->dropIndex('correspondences_archive_document_lookup');
            $table->dropColumn('archive_document_id');
        });
    }
};
