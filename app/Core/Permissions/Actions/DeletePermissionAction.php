<?php

namespace App\Core\Permissions\Actions;

use App\Core\Permissions\Models\Permission;
use Illuminate\Validation\ValidationException;

class DeletePermissionAction
{
    public function execute(Permission $permission): void
    {
        if ($permission->is_system) {
            throw ValidationException::withMessages([
                'permission' => 'مجوز سیستمی قابل حذف نیست.',
            ]);
        }

        $permission->delete();
    }
}