<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('permissions')->updateOrInsert(
            ['code' => 'tasks.refer'],
            [
                'name' => 'ارجاع تسک',
                'description' => 'ارجاع تسک به مسئول دیگر',
                'is_active' => true,
                'is_system' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('permissions')
            ->where('code', 'tasks.refer')
            ->delete();
    }
};
