<?php

namespace App\Core\Roles\Actions;

use App\Core\Roles\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ToggleRoleStatusAction
{
    public function execute(Role $role): Role
    {
        if ($role->is_system) {
            throw new HttpException(
                403,
                'وضعیت نقش سیستمی قابل تغییر نیست.'
            );
        }

        $role->update([
            'is_active' => ! $role->is_active,
        ]);

        return $role->refresh();
    }
}