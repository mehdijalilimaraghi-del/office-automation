<?php

namespace App\Core\Permissions\Actions;

use App\Core\Permissions\Models\Permission;
use Illuminate\Validation\ValidationException;

class TogglePermissionStatusAction
{
    public function execute(Permission $permission): Permission
    {
        if ($permission->is_system) {
            throw ValidationException::withMessages([
                'permission' => 'وضعیت مجوز سیستمی قابل تغییر نیست.',
            ]);
        }

        $permission->update([
            'is_active' => ! $permission->is_active,
        ]);

        return $permission->refresh();
    }
}