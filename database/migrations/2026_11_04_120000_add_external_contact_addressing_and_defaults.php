<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('external_contacts', function (Blueprint $table): void {
            $table->string('organizational_title_prefix')->nullable();
            $table->string('personal_salutation')->nullable();
            $table->string('professional_title')->nullable();
            $table->boolean('is_default')->default(false);
        });

        Schema::table('correspondences', function (Blueprint $table): void {
            $table->foreignId('external_contact_id')->nullable()->constrained('external_contacts')->noActionOnDelete();
            $table->longText('recipient_addressing_snapshot')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('correspondences', function (Blueprint $table): void {
            $table->dropForeign(['external_contact_id']);
            $table->dropColumn(['external_contact_id', 'recipient_addressing_snapshot']);
        });

        Schema::table('external_contacts', function (Blueprint $table): void {
            $table->dropColumn(['organizational_title_prefix', 'personal_salutation', 'professional_title', 'is_default']);
        });
    }
};
