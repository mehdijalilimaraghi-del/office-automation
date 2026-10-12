<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('departments')
            ->where('is_system_default', true)
            ->update(['is_system_default' => false]);
    }

    public function down(): void
    {
        // Department rows are retained as ordinary organization data.
    }
};
