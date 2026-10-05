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
    /**
     * Create a starter role only when it does not exist.
     *
     * Existing customizations to starter roles are intentionally preserved.
     *
     * @param array<string, PermissionScope> $permissions
     */
    private function createDefaultRole(
        string $code,
        string $name,
        string $description,
        array $permissions,
    ): void {
        $role = Role::firstOrCreate(
            ['code' => $code],
            [
                'name' => $name,
                'description' => $description,
                'is_active' => true,
                'is_system' => false,
            ],
        );

        if (! $role->wasRecentlyCreated) {
            return;
        }

        $permissionCodes = array_keys($permissions);

        $permissionModels = Permission::query()
            ->where('is_active', true)
            ->whereIn('code', $permissionCodes)
            ->get()
            ->keyBy('code');

        $syncData = [];

        foreach ($permissions as $permissionCode => $scope) {
            $permission = $permissionModels->get($permissionCode);

            if (! $permission) {
                continue;
            }

            $syncData[$permission->id] = [
                'scope' => $scope->value,
            ];
        }

        $role->permissions()->sync($syncData);
    }


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

        $this->createDefaultRole(
            'EMPLOYEE',
            'کاربر عادی',
            'دسترسی‌های پایه برای کاربران عملیاتی سازمان',
            [
                'notifications.view' => PermissionScope::Organization,
                'cartable.view' => PermissionScope::Organization,
                'projects.view' => PermissionScope::Organization,
                'tasks.view' => PermissionScope::Self,
                'tasks.create' => PermissionScope::Project,
                'tasks.update' => PermissionScope::Self,
                'tasks.change_status' => PermissionScope::Self,
                'tasks.submit' => PermissionScope::Self,
                'tasks.comment' => PermissionScope::Self,
                'tasks.log_work' => PermissionScope::Self,
                'tasks.create_reminder' => PermissionScope::Self,
                'documents.view' => PermissionScope::Organization,
            ],
        );

        $this->createDefaultRole(
            'PROJECT_MANAGER',
            'مدیر پروژه',
            'مدیریت پروژه‌ها و اجرای کارها در محدوده پروژه‌های تحت مدیریت یا عضویت',
            [
                'notifications.view' => PermissionScope::Organization,
                'cartable.view' => PermissionScope::Organization,
                'projects.view' => PermissionScope::Organization,
                'projects.create' => PermissionScope::Organization,
                'projects.update' => PermissionScope::Organization,
                'projects.manage_roles' => PermissionScope::Organization,
                'projects.manage_members' => PermissionScope::Organization,
                'tasks.view' => PermissionScope::Project,
                'tasks.create' => PermissionScope::Project,
                'tasks.update' => PermissionScope::Project,
                'tasks.assign' => PermissionScope::Project,
                'tasks.refer' => PermissionScope::Project,
                'tasks.change_status' => PermissionScope::Project,
                'tasks.submit' => PermissionScope::Project,
                'tasks.review' => PermissionScope::Project,
                'tasks.comment' => PermissionScope::Project,
                'tasks.log_work' => PermissionScope::Project,
                'tasks.create_reminder' => PermissionScope::Project,
                'documents.view' => PermissionScope::Organization,
            ],
        );

        $this->createDefaultRole(
            'DEPARTMENT_MANAGER',
            'مدیر واحد',
            'مدیریت اعضا، سمت‌ها و فعالیت‌های واحد و زیرواحدهای آن',
            [
                'notifications.view' => PermissionScope::Organization,
                'cartable.view' => PermissionScope::Organization,
                'users.view' => PermissionScope::DepartmentTree,
                'users.manage_departments' => PermissionScope::DepartmentTree,
                'departments.view' => PermissionScope::DepartmentTree,
                'departments.manage_positions' => PermissionScope::DepartmentTree,
                'projects.view' => PermissionScope::DepartmentTree,
                'projects.manage_members' => PermissionScope::DepartmentTree,
                'tasks.view' => PermissionScope::DepartmentTree,
                'tasks.create' => PermissionScope::DepartmentTree,
                'tasks.update' => PermissionScope::DepartmentTree,
                'tasks.assign' => PermissionScope::DepartmentTree,
                'tasks.refer' => PermissionScope::DepartmentTree,
                'tasks.change_status' => PermissionScope::DepartmentTree,
                'tasks.submit' => PermissionScope::DepartmentTree,
                'tasks.review' => PermissionScope::DepartmentTree,
                'tasks.comment' => PermissionScope::DepartmentTree,
                'tasks.log_work' => PermissionScope::DepartmentTree,
                'tasks.create_reminder' => PermissionScope::DepartmentTree,
                'documents.view' => PermissionScope::DepartmentTree,
            ],
        );

        $this->createDefaultRole(
            'ORGANIZATION_MANAGER',
            'مدیر سازمان',
            'مدیریت عملیاتی کاربران، واحدها و نقش‌های سامانه بدون دسترسی مدیر سیستم',
            [
                'notifications.view' => PermissionScope::Organization,
                'cartable.view' => PermissionScope::Organization,
                'users.view' => PermissionScope::Organization,
                'users.create' => PermissionScope::Organization,
                'users.edit' => PermissionScope::Organization,
                'users.toggle_status' => PermissionScope::Organization,
                'users.manage_roles' => PermissionScope::Organization,
                'users.manage_departments' => PermissionScope::Organization,
                'users.invite' => PermissionScope::Organization,
                'departments.view' => PermissionScope::Organization,
                'departments.create' => PermissionScope::Organization,
                'departments.edit' => PermissionScope::Organization,
                'departments.delete' => PermissionScope::Organization,
                'departments.toggle_status' => PermissionScope::Organization,
                'departments.manage_positions' => PermissionScope::Organization,
                'roles.view' => PermissionScope::Organization,
                'projects.view' => PermissionScope::Organization,
                'projects.create' => PermissionScope::Organization,
                'projects.update' => PermissionScope::Organization,
                'projects.manage_roles' => PermissionScope::Organization,
                'projects.manage_members' => PermissionScope::Organization,
                'tasks.view' => PermissionScope::Organization,
                'tasks.create' => PermissionScope::Organization,
                'tasks.update' => PermissionScope::Organization,
                'tasks.assign' => PermissionScope::Organization,
                'tasks.refer' => PermissionScope::Organization,
                'tasks.change_status' => PermissionScope::Organization,
                'tasks.submit' => PermissionScope::Organization,
                'tasks.review' => PermissionScope::Organization,
                'tasks.comment' => PermissionScope::Organization,
                'tasks.log_work' => PermissionScope::Organization,
                'tasks.create_reminder' => PermissionScope::Organization,
                'documents.view' => PermissionScope::Organization,
            ],
        );

        $bootstrapEmail = trim((string) env('OFFICE_AUTOMATION_BOOTSTRAP_ADMIN_EMAIL'));
        $bootstrapPassword = (string) env('OFFICE_AUTOMATION_BOOTSTRAP_ADMIN_PASSWORD');

        if ($bootstrapEmail === '' || $bootstrapPassword === '') {
            return;
        }

        $email = strtolower($bootstrapEmail);
        $user = User::query()->where('email', $email)->first();

        if ($user?->status === 'inactive') {
            return;
        }

        $user ??= new User([
            'email' => $email,
            'username' => 'systemadmin',
        ]);

        $user->username ??= 'systemadmin';
        $user->name = (string) env('OFFICE_AUTOMATION_BOOTSTRAP_ADMIN_NAME', 'مدیر سیستم');
        $user->password = Hash::make($bootstrapPassword);
        $user->status = 'active';
        $user->save();

        $user->roles()->syncWithoutDetaching([$role->id]);
    }
}