<?php

declare(strict_types=1);

namespace App\Core\Settings\Services;

use App\Core\Departments\Models\Department;
use App\Core\Settings\Models\SystemSetting;
use App\Models\User;
use InvalidArgumentException;

class OrganizationDepartmentRoleService
{
    public const MANAGEMENT = 'management';
    public const PROJECT_MANAGEMENT = 'project_management';
    public const SECRETARIAT = 'secretariat';

    public const ROLES = [
        self::MANAGEMENT,
        self::PROJECT_MANAGEMENT,
        self::SECRETARIAT,
    ];

    public function departmentId(string $role): ?int
    {
        if (! in_array($role, self::ROLES, true)) {
            throw new InvalidArgumentException("Unknown organization department role [{$role}].");
        }

        $value = SystemSetting::query()
            ->where('key', "organization.department_roles.{$role}")
            ->value('value');

        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }

    public function activeDepartmentId(string $role): ?int
    {
        $id = $this->departmentId($role);

        if ($id === null) {
            return null;
        }

        $department = Department::query()
            ->whereKey($id)
            ->where('is_active', true)
            ->first(['id', 'parent_id']);

        if ($department === null) {
            return null;
        }

        if ($role === self::PROJECT_MANAGEMENT) {
            $managementDepartmentId = $this->activeDepartmentId(self::MANAGEMENT);

            if (
                $managementDepartmentId !== null
                && (int) $department->parent_id !== $managementDepartmentId
            ) {
                return null;
            }
        }

        return (int) $department->id;
    }

    public function isAssigned(Department $department, string $role): bool
    {
        return $department->is_active
            && $this->activeDepartmentId($role) === (int) $department->id;
    }

    public function userBelongsTo(User $user, string $role): bool
    {
        $departmentId = $this->activeDepartmentId($role);

        if ($departmentId === null) {
            return false;
        }

        return $user->departments()
            ->whereKey($departmentId)
            ->where('departments.is_active', true)
            ->exists()
            || $user->managedDepartments()
                ->whereKey($departmentId)
                ->where('departments.is_active', true)
                ->exists();
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            self::MANAGEMENT => 'واحد مدیریتی',
            self::PROJECT_MANAGEMENT => 'واحد مدیریت پروژه‌ها',
            self::SECRETARIAT => 'دبیرخانه',
        ];
    }

    public static function settingKey(string $role): string
    {
        if (! in_array($role, self::ROLES, true)) {
            throw new InvalidArgumentException("Unknown organization department role [{$role}].");
        }

        return "organization.department_roles.{$role}";
    }
}
