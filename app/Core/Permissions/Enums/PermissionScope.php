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

    /**
     * Return the scopes that have a defined authorization meaning for a permission.
     *
     * Only task and project permissions are currently scope-aware in V1.
     * Other permissions intentionally remain organization-wide.
     *
     * @return list<self>
     */
    public static function allowedForPermission(string $permissionCode): array
    {
        if (
            str_starts_with($permissionCode, 'tasks.')
            || str_starts_with($permissionCode, 'projects.')
        ) {
            return [
                self::Self,
                self::Department,
                self::DepartmentTree,
                self::Project,
                self::Organization,
            ];
        }

        return [self::Organization];
    }

    public static function allows(string $permissionCode, self $scope): bool
    {
        return in_array($scope, self::allowedForPermission($permissionCode), true);
    }
}
