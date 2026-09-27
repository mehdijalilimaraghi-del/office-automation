<?php

namespace App\Core\Permissions\Actions;

use App\Core\Permissions\Models\Permission;
use Illuminate\Validation\ValidationException;

class UpdatePermissionAction
{
    public function execute(
        Permission $permission,
        array $data
    ): Permission {
        if ($permission->is_system) {
            throw ValidationException::withMessages([
                'permission' => 'مجوز سیستمی قابل ویرایش نیست.',
            ]);
        }

        $permission->update([
            'name' => $data['name'],
            'code' => $this->normalizeCode($data['code']),
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);

        return $permission->refresh();
    }

    private function normalizeCode(string $code): string
    {
        return strtolower(trim($code));
    }
}