<?php

namespace App\Core\Roles\Actions;

use App\Core\Permissions\Enums\PermissionScope;
use App\Core\Roles\Models\Role;
use Illuminate\Support\Arr;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SyncRolePermissionsAction
{
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

            $scope = is_array($permission)
                ? ($permission['scope'] ?? null)
                : null;

            $scope ??= PermissionScope::defaultForPermission(
                $permissionModel->code
            )->value;

            $syncData[$permissionId] = [
                'scope' => $scope,
            ];
        }

        $role->permissions()->sync($syncData);

        return $role->load('permissions');
    }
}
