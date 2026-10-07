<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_external_contacts', function (Blueprint $table): void {
            $table->foreignId('external_organization_id')
                ->nullable()
                ->after('external_contact_id')
                ->constrained('external_organizations')
                ->noActionOnDelete();

            $table->foreignId('external_department_id')
                ->nullable()
                ->after('external_organization_id')
                ->constrained('external_departments')
                ->noActionOnDelete();

            $table->index(['task_id', 'external_organization_id', 'external_department_id']);
        });
    }

    public function down(): void
    {
        Schema::table('task_external_contacts', function (Blueprint $table): void {
            $table->dropIndex(['task_id', 'external_organization_id', 'external_department_id']);
            $table->dropForeign(['external_department_id']);
            $table->dropForeign(['external_organization_id']);
            $table->dropColumn(['external_department_id', 'external_organization_id']);
        });
    }
};