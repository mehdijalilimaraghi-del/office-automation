<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const PERMISSIONS = [
        [
            'name' => 'مشاهده پروژه‌ها',
            'code' => 'projects.view',
            'description' => 'مشاهده پروژه‌ها',
        ],
        [
            'name' => 'ایجاد پروژه',
            'code' => 'projects.create',
            'description' => 'ایجاد پروژه جدید',
        ],
        [
            'name' => 'ویرایش پروژه',
            'code' => 'projects.update',
            'description' => 'ویرایش اطلاعات پروژه',
        ],
        [
            'name' => 'حذف پروژه',
            'code' => 'projects.delete',
            'description' => 'حذف پروژه',
        ],
        [
            'name' => 'مدیریت نقش‌های پروژه',
            'code' => 'projects.manage_roles',
            'description' => 'ایجاد، ویرایش، فعال‌سازی و حذف نقش‌های پروژه',
        ],
        [
            'name' => 'مدیریت اعضای پروژه',
            'code' => 'projects.manage_members',
            'description' => 'افزودن، تغییر نقش و حذف اعضای پروژه',
        ],
        [
            'name' => 'مشاهده تسک‌ها',
            'code' => 'tasks.view',
            'description' => 'مشاهده تسک‌ها',
        ],
        [
            'name' => 'ایجاد تسک',
            'code' => 'tasks.create',
            'description' => 'ایجاد تسک جدید',
        ],
        [
            'name' => 'ویرایش تسک',
            'code' => 'tasks.update',
            'description' => 'ویرایش اطلاعات تسک',
        ],
        [
            'name' => 'تخصیص تسک',
            'code' => 'tasks.assign',
            'description' => 'تغییر مسئول انجام تسک',
        ],
        [
            'name' => 'تغییر وضعیت تسک',
            'code' => 'tasks.change_status',
            'description' => 'تغییر وضعیت تسک',
        ],
        [
            'name' => 'حذف تسک',
            'code' => 'tasks.delete',
            'description' => 'حذف تسک',
        ],
    ];

    public function up(): void
    {
        $now = now();

        foreach (self::PERMISSIONS as $permission) {
            DB::table('permissions')->updateOrInsert(
                [
                    'code' => $permission['code'],
                ],
                [
                    'name' => $permission['name'],
                    'description' => $permission['description'],
                    'is_active' => true,
                    'is_system' => true,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }
    }

    public function down(): void
    {
        DB::table('permissions')
            ->whereIn(
                'code',
                array_column(self::PERMISSIONS, 'code')
            )
            ->delete();
    }
};