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
            $table->unsignedBigInteger('task_id')->nullable();
            $table->string('external_registration_number')->nullable();
            $table->date('external_registered_at')->nullable();
            $table->string('dispatch_method', 100)->nullable();
            $table->text('external_tracking_notes')->nullable();

            $table->foreign('task_id')->references('id')->on('tasks')->nullOnDelete();
            $table->index('task_id');
        });
    }

    public function down(): void
    {
        Schema::table('correspondences', function (Blueprint $table): void {
            $table->dropForeign(['task_id']);
            $table->dropIndex(['task_id']);
            $table->dropColumn([
                'task_id',
                'external_registration_number',
                'external_registered_at',
                'dispatch_method',
                'external_tracking_notes',
            ]);
        });
    }
};
