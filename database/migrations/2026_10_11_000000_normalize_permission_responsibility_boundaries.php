<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $retiredPermissionIds = DB::table('permissions')
            ->whereIn('code', ['notifications.manage', 'documents.manage_access'])
            ->pluck('id');

        if ($retiredPermissionIds->isNotEmpty()) {
            DB::table('permission_role')
                ->whereIn('permission_id', $retiredPermissionIds)
                ->delete();

            DB::table('permissions')
                ->whereIn('id', $retiredPermissionIds)
                ->update(['is_active' => false]);
        }

        $visibilityPermissionIds = DB::table('permissions')
            ->whereIn('code', ['projects.view', 'tasks.view'])
            ->pluck('id');

        if ($visibilityPermissionIds->isNotEmpty()) {
            $nonSystemRoleIds = DB::table('roles')
                ->where('is_system', false)
                ->pluck('id');

            if ($nonSystemRoleIds->isNotEmpty()) {
                DB::table('permission_role')
                    ->whereIn('permission_id', $visibilityPermissionIds)
                    ->whereIn('role_id', $nonSystemRoleIds)
                    ->delete();
            }
        }
    }

    public function down(): void
    {
        // Deliberately do not restore retired grants or responsibility-based
        // visibility grants: restoring them could silently reintroduce access.
        DB::table('permissions')
            ->whereIn('code', ['notifications.manage', 'documents.manage_access'])
            ->update(['is_active' => true]);
    }
};
