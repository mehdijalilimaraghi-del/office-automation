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
                'name' => 'مدیریت تنظیمات سازمان',
                'code' => 'settings.manage',
            ],

            [
                'name' => 'مشاهده اعلان‌ها',
                'code' => 'notifications.view',
            ],
            [
                'name' => 'مدیریت اعلان‌ها',
                'code' => 'notifications.manage',
            ],

            [
                'name' => 'مشاهده کارتابل',
                'code' => 'cartable.view',
            ],

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
                'name' => 'مدیریت عضویت کاربر در واحدها',
                'code' => 'users.manage_departments',
            ],
            [
                'name' => 'دعوت کاربر',
                'code' => 'users.invite',
            ],
            [
                'name' => 'مدیریت فیلدهای سفارشی',
                'code' => 'custom_fields.manage',
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
                'name' => 'مدیریت سمت‌های سازمانی',
                'code' => 'departments.manage_positions',
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
                'name' => 'مشاهده پروژه‌ها',
                'code' => 'projects.view',
            ],
            [
                'name' => 'ایجاد پروژه',
                'code' => 'projects.create',
            ],
            [
                'name' => 'ویرایش پروژه',
                'code' => 'projects.update',
            ],
            [
                'name' => 'حذف پروژه',
                'code' => 'projects.delete',
            ],
            [
                'name' => 'مدیریت نقش‌های پروژه',
                'code' => 'projects.manage_roles',
            ],
            [
                'name' => 'مدیریت اعضای پروژه',
                'code' => 'projects.manage_members',
            ],

            [
                'name' => 'مشاهده تسک‌ها',
                'code' => 'tasks.view',
            ],
            [
                'name' => 'ایجاد تسک',
                'code' => 'tasks.create',
            ],
            [
                'name' => 'ویرایش تسک',
                'code' => 'tasks.update',
            ],
            [
                'name' => 'حذف تسک',
                'code' => 'tasks.delete',
            ],
            [
                'name' => 'تخصیص تسک',
                'code' => 'tasks.assign',
            ],
            [
                'name' => 'ارجاع تسک',
                'code' => 'tasks.refer',
            ],
            [
                'name' => 'تغییر وضعیت تسک',
                'code' => 'tasks.change_status',
            ],
            [
                'name' => 'ارسال تسک برای بررسی',
                'code' => 'tasks.submit',
            ],
            [
                'name' => 'بررسی و تأیید تسک',
                'code' => 'tasks.review',
            ],
            [
                'name' => 'ثبت کامنت تسک',
                'code' => 'tasks.comment',
            ],
            [
                'name' => 'ثبت گزارش فعالیت تسک',
                'code' => 'tasks.log_work',
            ],
            [
                'name' => 'ایجاد یادآور تسک',
                'code' => 'tasks.create_reminder',
            ],

            [
                'name' => 'مشاهده طرف‌های خارجی',
                'code' => 'external_parties.view',
            ],
            [
                'name' => 'ایجاد طرف خارجی',
                'code' => 'external_parties.create',
            ],
            [
                'name' => 'ویرایش طرف خارجی',
                'code' => 'external_parties.edit',
            ],
            [
                'name' => 'حذف طرف خارجی',
                'code' => 'external_parties.delete',
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

            [
                'name' => 'مشاهده اسناد',
                'code' => 'documents.view',
            ],
            [
                'name' => 'ایجاد سند',
                'code' => 'documents.create',
            ],
            [
                'name' => 'ویرایش سند',
                'code' => 'documents.update',
            ],
            [
                'name' => 'حذف سند',
                'code' => 'documents.delete',
            ],
            [
                'name' => 'مدیریت دسترسی اسناد',
                'code' => 'documents.manage_access',
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

        // Retire permissions that have no active route or UI action. Keep the
        // records for audit/history, but prevent them from being granted.
        $retiredCodes = [
            'notifications.manage',
            'documents.manage_access',
        ];

        Permission::query()
            ->whereIn('code', $retiredCodes)
            ->get()
            ->each(function (Permission $permission): void {
                $permission->roles()->detach();
                $permission->update(['is_active' => false]);
            });

        // Project and work-order operations are derived from actual
        // responsibilities. Keep them out of administrative roles. The system
        // role is reseeded with all active permissions by CoreRoleSeeder.
        $responsibilityCodes = [
            'projects.view',
            'projects.update',
            'projects.manage_roles',
            'projects.manage_members',
            'tasks.view',
            'tasks.create',
            'tasks.update',
            'tasks.assign',
            'tasks.refer',
            'tasks.change_status',
            'tasks.submit',
            'tasks.review',
            'tasks.comment',
            'tasks.log_work',
            'tasks.create_reminder',
        ];

        Permission::query()
            ->whereIn('code', $responsibilityCodes)
            ->get()
            ->each(function (Permission $permission): void {
                $permission->roles()
                    ->where('roles.is_system', false)
                    ->detach();
            });
    }
}