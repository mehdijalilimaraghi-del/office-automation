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
            $table->foreignId('archive_department_id')->nullable()->constrained('departments');
            $table->foreignId('related_department_id')->nullable()->constrained('departments');
            $table->boolean('archive_restricted')->default(true);
            $table->index(['related_department_id', 'archive_restricted']);
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table): void {
            $table->dropForeign(['archive_department_id']);
            $table->dropForeign(['related_department_id']);
            $table->dropIndex(['related_department_id', 'archive_restricted']);
            $table->dropColumn(['archive_department_id', 'related_department_id', 'archive_restricted']);
        });
    }
};
