<?php

namespace Database\Seeders;

use App\Core\Permissions\Models\Permission;
use Illuminate\Database\Seeder;

class CorePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            [
                'name' => 'مشاهده کاربران',
                'code' => 'users.view',
            ],
            [
                'name' => 'ایجاد کاربر',
                'code' => 'users.create',
            ],
            [
                'name' => 'ویرایش کاربر',
                'code' => 'users.edit',
            ],
            [
                'name' => 'حذف کاربر',
                'code' => 'users.delete',
            ],
            [
                'name' => 'تغییر وضعیت کاربر',
                'code' => 'users.toggle_status',
            ],
            [
                'name' => 'مدیریت نقش‌های کاربر',
                'code' => 'users.manage_roles',
            ],

            [
                'name' => 'مشاهده واحدها',
                'code' => 'departments.view',
            ],
            [
                'name' => 'ایجاد واحد',
                'code' => 'departments.create',
            ],
            [
                'name' => 'ویرایش واحد',
                'code' => 'departments.edit',
            ],
            [
                'name' => 'حذف واحد',
                'code' => 'departments.delete',
            ],
            [
                'name' => 'تغییر وضعیت واحد',
                'code' => 'departments.toggle_status',
            ],

            [
                'name' => 'مشاهده نقش‌ها',
                'code' => 'roles.view',
            ],
            [
                'name' => 'ایجاد نقش',
                'code' => 'roles.create',
            ],
            [
                'name' => 'ویرایش نقش',
                'code' => 'roles.edit',
            ],
            [
                'name' => 'حذف نقش',
                'code' => 'roles.delete',
            ],
            [
                'name' => 'تغییر وضعیت نقش',
                'code' => 'roles.toggle_status',
            ],
            [
                'name' => 'مدیریت مجوزهای نقش',
                'code' => 'roles.manage_permissions',
            ],

            [
                'name' => 'مشاهده مجوزها',
                'code' => 'permissions.view',
            ],
            [
                'name' => 'ایجاد مجوز',
                'code' => 'permissions.create',
            ],
            [
                'name' => 'ویرایش مجوز',
                'code' => 'permissions.edit',
            ],
            [
                'name' => 'حذف مجوز',
                'code' => 'permissions.delete',
            ],
            [
                'name' => 'تغییر وضعیت مجوز',
                'code' => 'permissions.toggle_status',
            ],
        ];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(
                [
                    'code' => $permission['code'],
                ],
                [
                    'name' => $permission['name'],
                    'is_active' => true,
                    'is_system' => true,
                ]
            );
        }
    }
}