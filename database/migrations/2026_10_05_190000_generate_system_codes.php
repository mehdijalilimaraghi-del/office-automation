<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->backfill('departments', 'ORG');
        $this->backfill('positions', 'POS');
        $this->backfill('roles', 'ROLE', fn ($query) => $query->where('is_system', false));
        $this->backfill('projects', 'PRJ');
        $this->backfill('project_roles', 'PRL');
    }

    public function down(): void
    {
        // The previous codes are user-defined values and cannot be reconstructed
        // safely after this migration. Generated system codes remain in place.
    }

    private function backfill(
        string $table,
        string $prefix,
        ?callable $filter = null,
    ): void {
        $query = DB::table($table)->orderBy('id');

        if ($filter !== null) {
            $filter($query);
        }

        $query->get(['id'])->each(function (object $row) use ($table, $prefix): void {
            DB::table($table)
                ->where('id', $row->id)
                ->update([
                    'code' => sprintf('%s-%06d', $prefix, (int) $row->id),
                ]);
        });
    }
};
