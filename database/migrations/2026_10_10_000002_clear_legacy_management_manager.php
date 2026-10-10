<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('departments')
            ->where('code', 'ORG-000001')
            ->update([
                'is_system_default' => false,
                'manager_id' => null,
            ]);
    }

    public function down(): void
    {
        // Intentionally do not restore the legacy department's default flag or manager.
    }
};
