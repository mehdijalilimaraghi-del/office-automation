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
            $table->string('appendix_mode', 20)->default('editor')->after('attachment_pages');
            $table->longText('appendix_body')->nullable()->after('appendix_mode');
        });
    }

    public function down(): void
    {
        Schema::table('correspondences', function (Blueprint $table): void {
            $table->dropColumn(['appendix_mode', 'appendix_body']);
        });
    }
};
