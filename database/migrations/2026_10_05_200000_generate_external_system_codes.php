<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->backfill('external_organizations', 'EXTORG');
        $this->backfill('external_departments', 'EXTDPT');
        $this->backfill('external_contacts', 'EXTCNT');
    }

    public function down(): void
    {
        // Generated system codes are intentionally retained.
    }

    private function backfill(string $table, string $prefix): void
    {
        DB::table($table)
            ->orderBy('id')
            ->get(['id'])
            ->each(function (object $row) use ($table, $prefix): void {
                DB::table($table)
                    ->where('id', $row->id)
                    ->update([
                        'code' => sprintf('%s-%06d', $prefix, (int) $row->id),
                    ]);
            });
    }
};
