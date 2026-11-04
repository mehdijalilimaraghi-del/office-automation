<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('departments', function (Blueprint $table): void {
            $table->string('manager_signature_path')->nullable();
            $table->boolean('manager_signature_active')->default(false);
        });

        Schema::table('correspondences', function (Blueprint $table): void {
            $table->longText('document_identity_snapshot')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('correspondences', function (Blueprint $table): void {
            $table->dropColumn('document_identity_snapshot');
        });

        Schema::table('departments', function (Blueprint $table): void {
            $table->dropColumn(['manager_signature_path', 'manager_signature_active']);
        });
    }
};
