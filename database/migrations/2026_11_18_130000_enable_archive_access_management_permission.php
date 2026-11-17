<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = DB::table('permissions');
        $attributes = ['name' => 'مدیریت دسترسی اسناد', 'is_active' => true, 'is_system' => true, 'updated_at' => now()];

        if ($permissions->where('code', 'documents.manage_access')->exists()) {
            $permissions->update($attributes);
        } else {
            DB::table('permissions')->insert([
                ...$attributes,
                'code' => 'documents.manage_access',
                'created_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('permissions')->where('code', 'documents.manage_access')->update(['is_active' => false, 'updated_at' => now()]);
    }
};
