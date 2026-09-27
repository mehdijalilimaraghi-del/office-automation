<?php

namespace App\Core\Roles\Actions;

use App\Core\Roles\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SyncRolePermissionsAction
{
    public function execute(
        Role $role,
        array $permissionIds
    ): Role {
        if ($role->is_system) {
            throw new HttpException(
                403,
                'مجوزهای نقش سیستمی قابل تغییر نیستند.'
            );
        }

        $role->permissions()->sync($permissionIds);

        return $role->load('permissions');
    }
}