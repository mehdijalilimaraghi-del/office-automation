<?php

namespace App\Core\Roles\Actions;

use App\Core\Roles\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;

class DeleteRoleAction
{
    public function execute(Role $role): void
    {
        if ($role->is_system) {
            throw new HttpException(
                403,
                'نقش سیستمی قابل حذف نیست.'
            );
        }

        $role->delete();
    }
}