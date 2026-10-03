<?php

namespace Database\Seeders;

use App\Core\Permissions\Enums\PermissionScope;
use App\Core\Permissions\Models\Permission;
use App\Core\Roles\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class CoreRoleSeeder extends Seeder
{
    public function run(): void
    {
        $role = Role::updateOrCreate(
            ['code' => 'SYSTEM_ADMIN'],
            [
                'name' => 'مدیر سیستم',
                'description' => 'دسترسی کامل به امکانات سامانه',
                'is_active' => true,
                'is_system' => true,
            ]
        );

        $permissions = Permission::query()
            ->where('is_active', true)
            ->get();

        $syncData = $permissions
            ->mapWithKeys(
                fn (Permission $permission): array => [
                    $permission->id => [
                        'scope' => PermissionScope::Organization->value,
                    ],
                ]
            )
            ->all();

        $role->permissions()->sync($syncData);

        $bootstrapEmail = trim((string) env('OFFICE_AUTOMATION_BOOTSTRAP_ADMIN_EMAIL'));

        if ($bootstrapEmail === '') {
            return;
        }

        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [strtolower($bootstrapEmail)])
            ->first();

        if ($user !== null && $user->isActive()) {
            $user->roles()->syncWithoutDetaching([$role->id]);
        }
    }
}
