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
            $table->renameColumn('department_id', 'recipient_department_id');
        });

        Schema::table('correspondences', function (Blueprint $table): void {
            $table->foreignId('created_department_id')
                ->nullable()
                ->after('created_by')
                ->constrained('departments')
                ->noActionOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('correspondences', function (Blueprint $table): void {
            $table->dropForeign(['created_department_id']);
            $table->dropColumn('created_department_id');
        });

        Schema::table('correspondences', function (Blueprint $table): void {
            $table->renameColumn('recipient_department_id', 'department_id');
        });
    }
};
