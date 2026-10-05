<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('external_contacts', 'code')) {
            Schema::table('external_contacts', function (Blueprint $table): void {
                $table->string('code', 100)->nullable();
            });
        }

        DB::table('external_contacts')
            ->whereNull('code')
            ->orderBy('id')
            ->get(['id'])
            ->each(function (object $contact): void {
                DB::table('external_contacts')
                    ->where('id', $contact->id)
                    ->update([
                        'code' => sprintf('EXTCNT-%06d', (int) $contact->id),
                    ]);
            });
    }

    public function down(): void
    {
        // Generated system codes are intentionally retained.
    }
};
