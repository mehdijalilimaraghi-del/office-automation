<?php

namespace App\Core\Roles\Actions;

use App\Core\Roles\Models\Role;
use App\Core\Audit\Contracts\AuditServiceInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;

class DeleteRoleAction
{
    public function __construct(private readonly AuditServiceInterface $audit)
    {
    }

    public function execute(Role $role): void
    {
        if ($role->is_system) {
            throw new HttpException(
                403,
                'نقش سیستمی قابل حذف نیست.'
            );
        }

        $this->audit->record('role.deleted', $role, ['name' => $role->name, 'code' => $role->code], null);
        $role->delete();
    }
}