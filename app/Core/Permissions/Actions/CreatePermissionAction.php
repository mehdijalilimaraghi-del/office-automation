<?php

namespace App\Core\Permissions\Actions;

use App\Core\Permissions\Models\Permission;

class CreatePermissionAction
{
    public function execute(array $data): Permission
    {
        return Permission::create([
            'name' => $data['name'],
            'code' => $this->normalizeCode($data['code']),
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'is_system' => false,
        ]);
    }

    private function normalizeCode(string $code): string
    {
        return strtolower(trim($code));
    }
}