<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('correspondence_numbering_scoped_counters', function (Blueprint $table): void {
            $table->id();
            $table->string('counter_type', 24);
            $table->string('scope_key', 64);
            $table->unsignedBigInteger('last_number')->default(0);
            $table->timestamps();
            $table->unique(['counter_type', 'scope_key'], 'corr_number_scope_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('correspondence_numbering_scoped_counters');
    }
};
