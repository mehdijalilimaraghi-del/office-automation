<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('task_activities', 'started_at')) {
            Schema::table('task_activities', function (Blueprint $table): void {
                $table->timestamp('started_at')->nullable();
            });
        }

        if (! Schema::hasTable('task_work_logs')) {
            return;
        }

        $nextSequenceByTask = [];

        foreach (DB::table('task_work_logs')->orderBy('id')->cursor() as $workLog) {
            $taskId = (int) $workLog->task_id;

            if (! array_key_exists($taskId, $nextSequenceByTask)) {
                $nextSequenceByTask[$taskId] = ((int) DB::table('task_activities')
                    ->where('task_id', $taskId)
                    ->max('sequence')) + 1;
            }

            $userId = $workLog->user_id !== null ? (int) $workLog->user_id : null;
            $creatorId = $userId ?? (int) DB::table('tasks')->where('id', $taskId)->value('created_by');

            DB::table('task_activities')->insert([
                'task_id' => $taskId,
                'title' => $workLog->title,
                'description' => $workLog->description,
                'status' => 'completed',
                'activity_type' => 'logged',
                'sequence' => $nextSequenceByTask[$taskId]++,
                'assigned_to' => null,
                'created_by' => $creatorId,
                'completed_by' => $userId,
                'started_at' => $workLog->started_at,
                'completed_at' => $workLog->completed_at,
                'created_at' => $workLog->created_at,
                'updated_at' => $workLog->updated_at,
            ]);
        }

        Schema::drop('task_work_logs');
    }

    public function down(): void
    {
        if (! Schema::hasTable('task_work_logs')) {
            Schema::create('task_work_logs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users');
                $table->string('title', 200);
                $table->text('description')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
                $table->index(['task_id', 'created_at']);
                $table->index('user_id');
            });
        }

        foreach (DB::table('task_activities')->where('activity_type', 'logged')->orderBy('id')->cursor() as $activity) {
            DB::table('task_work_logs')->insert([
                'task_id' => $activity->task_id,
                'user_id' => $activity->completed_by,
                'title' => $activity->title,
                'description' => $activity->description,
                'started_at' => $activity->started_at ?? null,
                'completed_at' => $activity->completed_at,
                'created_at' => $activity->created_at,
                'updated_at' => $activity->updated_at,
            ]);
        }

        DB::table('task_activities')->where('activity_type', 'logged')->delete();

        if (Schema::hasColumn('task_activities', 'started_at')) {
            Schema::table('task_activities', function (Blueprint $table): void {
                $table->dropColumn('started_at');
            });
        }
    }
};
