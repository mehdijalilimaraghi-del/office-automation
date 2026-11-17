<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('correspondence_letter_number_counters', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
            $table->unsignedBigInteger('last_number')->default(0);
            $table->timestamps();
        });

        DB::table('correspondence_letter_number_counters')->insert([
            'id' => 1,
            'last_number' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::table('correspondences', function (Blueprint $table): void {
            $table->string('letter_number', 100)->nullable();
            $table->index('letter_number');
        });
    }

    public function down(): void
    {
        Schema::table('correspondences', function (Blueprint $table): void {
            $table->dropIndex(['letter_number']);
            $table->dropColumn('letter_number');
        });

        Schema::dropIfExists('correspondence_letter_number_counters');
    }
};
