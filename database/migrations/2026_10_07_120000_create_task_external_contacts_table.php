<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_external_contacts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('external_contact_id')->constrained('external_contacts')->noActionOnDelete();
            $table->timestamps();

            $table->unique(['task_id', 'external_contact_id']);
            $table->index(['external_contact_id', 'task_id']);
        });

        DB::table('task_external_contacts')->insertUsing(
            ['task_id', 'external_contact_id', 'created_at', 'updated_at'],
            DB::table('tasks')
                ->select('id', 'external_contact_id', DB::raw('GETDATE()'), DB::raw('GETDATE()'))
                ->whereNotNull('external_contact_id')
        );

        Schema::table('tasks', function (Blueprint $table): void {
            $table->dropForeign(['external_contact_id']);
            $table->dropColumn('external_contact_id');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->foreignId('external_contact_id')
                ->nullable()
                ->after('external_department_id')
                ->constrained('external_contacts')
                ->noActionOnDelete();
        });

        DB::statement(
            'UPDATE tasks
             SET external_contact_id = (
                 SELECT TOP 1 task_external_contacts.external_contact_id
                 FROM task_external_contacts
                 WHERE task_external_contacts.task_id = tasks.id
                 ORDER BY task_external_contacts.id
             )'
        );

        Schema::dropIfExists('task_external_contacts');
    }
};
