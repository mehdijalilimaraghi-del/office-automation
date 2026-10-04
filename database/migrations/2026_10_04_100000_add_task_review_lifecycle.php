<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->boolean('review_required')->default(false)->after('priority');
            $table->string('review_status', 20)->default('not_required')->after('review_required');
            $table->foreignId('reviewed_by')->nullable()->after('assignee_id')->constrained('users')->nullOnDelete();
            $table->dateTime('review_requested_at')->nullable();
            $table->dateTime('reviewed_at')->nullable();
            $table->text('review_note')->nullable();

            $table->index(['review_required', 'review_status']);
            $table->index('reviewed_by');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['reviewed_by']);
            $table->dropIndex(['review_required', 'review_status']);
            $table->dropIndex(['reviewed_by']);
            $table->dropColumn([
                'review_required',
                'review_status',
                'reviewed_by',
                'review_requested_at',
                'reviewed_at',
                'review_note',
            ]);
        });
    }
};
