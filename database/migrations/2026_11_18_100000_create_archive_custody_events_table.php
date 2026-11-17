<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('archive_custody_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('document_id')->constrained('documents');
            $table->foreignId('actor_id')->constrained('users');
            $table->string('action', 20);
            $table->string('location')->nullable();
            $table->string('recipient')->nullable();
            $table->date('due_on')->nullable();
            $table->string('note', 500)->nullable();
            $table->timestamp('created_at');
            $table->index(['document_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('archive_custody_events');
    }
};
