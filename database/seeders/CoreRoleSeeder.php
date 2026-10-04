<?php

namespace Database\Seeders;

use App\Core\Permissions\Enums\PermissionScope;
use App\Core\Permissions\Models\Permission;
use App\Core\Roles\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

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

        $permissions = Permission::query()->where('is_active', true)->get();

        $syncData = $permissions->mapWithKeys(
            fn (Permission $permission): array => [
                $permission->id => ['scope' => PermissionScope::Organization->value],
            ]
        )->all();

        $role->permissions()->sync($syncData);

        $bootstrapEmail = trim((string) env('OFFICE_AUTOMATION_BOOTSTRAP_ADMIN_EMAIL'));
        $bootstrapPassword = (string) env('OFFICE_AUTOMATION_BOOTSTRAP_ADMIN_PASSWORD');

        if ($bootstrapEmail === '' || $bootstrapPassword === '') {
            return;
        }

        $user = User::updateOrCreate(
            ['email' => strtolower($bootstrapEmail)],
            [
                'name' => (string) env('OFFICE_AUTOMATION_BOOTSTRAP_ADMIN_NAME', 'مدیر سیستم'),
                'password' => Hash::make($bootstrapPassword),
                'status' => 'active',
            ]
        );

        $user->roles()->syncWithoutDetaching([$role->id]);
    }
}