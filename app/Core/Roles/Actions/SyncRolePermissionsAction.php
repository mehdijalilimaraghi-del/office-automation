<?php

namespace App\Core\Roles\Actions;

use App\Core\Permissions\Enums\PermissionScope;
use App\Core\Roles\Models\Role;
use App\Core\Audit\Contracts\AuditServiceInterface;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SyncRolePermissionsAction
{
    public function __construct(private readonly AuditServiceInterface $audit)
    {
    }

    public function execute(
        Role $role,
        array $permissions
    ): Role {
        if ($role->is_system) {
            throw new HttpException(
                403,
                'مجوزهای نقش سیستمی قابل تغییر نیستند.'
            );
        }

        $oldPermissionIds = $role->permissions()->pluck('permissions.id')->map(fn ($id) => (int) $id)->all();
        $syncData = [];

        foreach ($permissions as $permission) {
            $permissionId = is_array($permission)
                ? (int) ($permission['permission_id'] ?? 0)
                : (int) $permission;

            if ($permissionId < 1) {
                continue;
            }

            $permissionModel = $role->permissions()
                ->getRelated()
                ->newQuery()
                ->findOrFail($permissionId);

            if (! $permissionModel->is_active) {
                throw ValidationException::withMessages([
                    "permissions.{$permissionId}" => 'مجوز غیرفعال قابل اعطا نیست.',
                ]);
            }

            if (in_array($permissionModel->code, ['projects.view', 'tasks.view'], true)) {
                throw ValidationException::withMessages([
                    "permissions.{$permissionId}" => 'دسترسی مشاهدهٔ پروژه‌ها و دستورکارها از مسئولیت عملیاتی کاربر تعیین می‌شود و در نقش قابل اعطا نیست.',
                ]);
            }

            $scope = is_array($permission)
                ? ($permission['scope'] ?? null)
                : null;

            $scope ??= PermissionScope::defaultForPermission(
                $permissionModel->code
            )->value;

            $scopeEnum = PermissionScope::tryFrom((string) $scope);

            if ($scopeEnum === null || ! PermissionScope::allows($permissionModel->code, $scopeEnum)) {
                throw ValidationException::withMessages([
                    "permissions.{$permissionId}.scope" => 'محدوده دسترسی انتخاب‌شده برای این مجوز معتبر نیست.',
                ]);
            }

            $syncData[$permissionId] = [
                'scope' => $scopeEnum->value,
            ];
        }

        $role->permissions()->sync($syncData);

        $role = $role->load('permissions');
        $this->audit->record('role.permissions_synced', $role, ['permission_ids' => $oldPermissionIds], ['permission_ids' => array_values(array_map(fn ($id) => (int) $id, array_keys($syncData)))]);
        return $role;
    }
}
