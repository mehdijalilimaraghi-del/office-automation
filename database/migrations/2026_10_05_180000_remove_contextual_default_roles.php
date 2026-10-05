<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const CONTEXTUAL_ROLE_CODES = [
        'EMPLOYEE',
        'PROJECT_MANAGER',
        'DEPARTMENT_SUPERVISOR',
        'ORGANIZATION_MANAGER',
    ];

    public function up(): void
    {
        $roleIds = DB::table('roles')
            ->whereIn('code', self::CONTEXTUAL_ROLE_CODES)
            ->pluck('id');

        if ($roleIds->isEmpty()) {
            return;
        }

        DB::table('permission_role')
            ->whereIn('role_id', $roleIds)
            ->delete();

        DB::table('role_user')
            ->whereIn('role_id', $roleIds)
            ->delete();

        DB::table('roles')
            ->whereIn('id', $roleIds)
            ->delete();
    }

    public function down(): void
    {
        // These roles are intentionally contextual and are no longer seeded.
    }
};
