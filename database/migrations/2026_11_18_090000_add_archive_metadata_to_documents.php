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
            $table->string('archive_kind', 16)->nullable();
            $table->string('archive_location')->nullable();
            $table->date('retain_until')->nullable();
            $table->index(['archive_kind', 'is_active', 'created_by'], 'documents_archive_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table): void {
            $table->dropIndex('documents_archive_lookup_index');
            $table->dropColumn(['archive_kind', 'archive_location', 'retain_until']);
        });
    }
};
