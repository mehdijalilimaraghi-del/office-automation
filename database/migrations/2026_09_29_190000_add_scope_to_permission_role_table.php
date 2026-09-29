<?php

use App\Core\Permissions\Enums\PermissionScope;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('permission_role', function (Blueprint $table) {
            $table->string('scope', 30)
                ->default(PermissionScope::Organization->value)
                ->after('permission_id');

            $table->index('scope');
        });

        DB::table('permission_role')
            ->join('permissions', 'permissions.id', '=', 'permission_role.permission_id')
            ->where('permissions.code', 'like', 'tasks.%')
            ->update([
                'permission_role.scope' => PermissionScope::Project->value,
            ]);
    }

    public function down(): void
    {
        Schema::table('permission_role', function (Blueprint $table) {
            $table->dropIndex(['scope']);
            $table->dropColumn('scope');
        });
    }
};
