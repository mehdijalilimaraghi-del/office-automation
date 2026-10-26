<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('correspondences', function (Blueprint $table): void {
            $table->string('workflow_state', 40)->default('draft')->index();
            $table->unsignedBigInteger('current_department_id')->nullable()->index();
            $table->unsignedBigInteger('current_user_id')->nullable()->index();
            $table->foreign('current_department_id')->references('id')->on('departments');
            $table->foreign('current_user_id')->references('id')->on('users');
        });

        DB::table('correspondences')->update([
            'workflow_state' => DB::raw("CASE WHEN status = 'registered' THEN 'in_progress' ELSE 'awaiting_registration' END"),
        ]);
        DB::table('correspondences')->where('status', 'registered')->whereNotNull('recipient_department_id')
            ->update(['current_department_id' => DB::raw('recipient_department_id')]);
        DB::table('correspondences')->whereNull('current_department_id')->whereNotNull('created_department_id')
            ->update(['current_department_id' => DB::raw('created_department_id')]);

        Schema::create('correspondence_actions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('correspondence_id');
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('action_type', 60);
            $table->text('note')->nullable();
            $table->unsignedBigInteger('from_department_id')->nullable();
            $table->unsignedBigInteger('to_department_id')->nullable();
            $table->unsignedBigInteger('from_user_id')->nullable();
            $table->unsignedBigInteger('to_user_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('correspondence_id')->references('id')->on('correspondences');
            $table->foreign('actor_id')->references('id')->on('users');
            $table->foreign('from_department_id')->references('id')->on('departments');
            $table->foreign('to_department_id')->references('id')->on('departments');
            $table->foreign('from_user_id')->references('id')->on('users');
            $table->foreign('to_user_id')->references('id')->on('users');
            $table->index(['correspondence_id', 'created_at']);
            $table->index(['to_department_id', 'action_type']);
        });

        Schema::create('correspondence_copies', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('correspondence_id');
            $table->unsignedBigInteger('department_id');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('note', 1000)->nullable();
            $table->timestamps();

            $table->foreign('correspondence_id')->references('id')->on('correspondences');
            $table->foreign('department_id')->references('id')->on('departments');
            $table->foreign('created_by')->references('id')->on('users');
            $table->unique(['correspondence_id', 'department_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('correspondence_copies');
        Schema::dropIfExists('correspondence_actions');

        Schema::table('correspondences', function (Blueprint $table): void {
            $table->dropForeign(['current_department_id']);
            $table->dropForeign(['current_user_id']);
            $table->dropIndex(['workflow_state']);
            $table->dropIndex(['current_department_id']);
            $table->dropIndex(['current_user_id']);
            $table->dropColumn(['workflow_state', 'current_department_id', 'current_user_id']);
        });
    }
};
