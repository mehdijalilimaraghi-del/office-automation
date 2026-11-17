<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('archive_custody_events', function (Blueprint $table): void {
            $table->boolean('approval_required')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('archive_custody_events', function (Blueprint $table): void {
            $table->dropColumn('approval_required');
        });
    }
};
