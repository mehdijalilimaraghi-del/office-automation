<?php

declare(strict_types=1);

namespace App\Core\Permissions\Enums;

enum PermissionScope: string
{
    case Self = 'self';
    case Department = 'department';
    case DepartmentTree = 'department_tree';
    case Project = 'project';
    case Organization = 'organization';

    public function label(): string
    {
        return match ($this) {
            self::Self => 'فقط موارد مربوط به خود',
            self::Department => 'واحد',
            self::DepartmentTree => 'واحد و زیرواحدها',
            self::Project => 'پروژه',
            self::Organization => 'کل سازمان',
        };
    }

    public static function defaultForPermission(string $permissionCode): self
    {
        return str_starts_with($permissionCode, 'tasks.')
            ? self::Project
            : self::Organization;
    }
}
