<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('document_id')->constrained('documents');
            $table->morphs('linkable');
            $table->string('purpose', 100)->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->unique(
                ['document_id', 'linkable_type', 'linkable_id'],
                'document_links_unique_target'
            );
            $table->index(['linkable_type', 'linkable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_links');
    }
};