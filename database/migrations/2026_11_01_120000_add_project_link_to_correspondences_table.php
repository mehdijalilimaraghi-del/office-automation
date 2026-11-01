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
            $table->unsignedBigInteger('project_id')->nullable();
            $table->foreign('project_id')->references('id')->on('projects')->noActionOnDelete();
            $table->index('project_id');
        });
    }

    public function down(): void
    {
        Schema::table('correspondences', function (Blueprint $table): void {
            $table->dropForeign(['project_id']);
            $table->dropIndex(['project_id']);
            $table->dropColumn('project_id');
        });
    }
};
