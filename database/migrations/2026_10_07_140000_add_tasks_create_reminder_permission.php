<?php

declare(strict_types=1);

use IlluminateDatabaseMigrationsMigration;
use IlluminateSupportFacadesDB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('permissions')->updateOrInsert(
            ['code' => 'tasks.create_reminder'],
            [
                'name' => 'ایجاد یادآوری وظیفه',
                'description' => 'ثبت یادآوری برای وظیفه',
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
            ->where('code', 'tasks.create_reminder')
            ->delete();
    }
};
