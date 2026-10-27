<?php

use App\Modules\Correspondence\Models\Correspondence;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('correspondence_related_letters', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('correspondence_id')->constrained('correspondences')->cascadeOnDelete();
            $table->foreignId('related_correspondence_id')->constrained('correspondences')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['correspondence_id', 'related_correspondence_id'], 'correspondence_related_letters_pair_unique');
            $table->index('related_correspondence_id', 'correspondence_related_letters_related_index');
        });

        Correspondence::query()
            ->whereNotNull('reply_to_id')
            ->select(['id', 'reply_to_id'])
            ->orderBy('id')
            ->chunkById(500, function ($letters): void {
                $now = now();
                DB::table('correspondence_related_letters')->insertOrIgnore(
                    $letters->map(fn ($letter): array => [
                        'correspondence_id' => $letter->id,
                        'related_correspondence_id' => $letter->reply_to_id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->all(),
                );
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('correspondence_related_letters');
    }
};
