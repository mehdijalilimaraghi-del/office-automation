<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->string('sub_status', 40)->nullable()->after('status');
        });

        DB::table('tasks')->where('status', 'submitted')->update([
            'status' => 'in_progress',
            'sub_status' => 'manager_review',
        ]);

        DB::table('tasks')->where('review_status', 'rejected')->where('status', 'in_progress')->update([
            'sub_status' => 'returned_for_completion',
        ]);
    }

    public function down(): void
    {
        DB::table('tasks')->where('sub_status', 'manager_review')->where('review_status', 'pending')->update([
            'status' => 'submitted',
        ]);

        Schema::table('tasks', function (Blueprint $table): void {
            $table->dropColumn('sub_status');
        });
    }
};
