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
            $table->foreignId('external_organization_id')->nullable()->constrained('external_organizations')->noActionOnDelete();
            $table->foreignId('external_department_id')->nullable()->constrained('external_departments')->noActionOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('correspondences', function (Blueprint $table): void {
            $table->dropForeign(['external_department_id']);
            $table->dropForeign(['external_organization_id']);
            $table->dropColumn(['external_department_id', 'external_organization_id']);
        });
    }
};
