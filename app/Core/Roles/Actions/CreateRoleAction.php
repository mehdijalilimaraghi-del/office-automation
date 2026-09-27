<?php

namespace App\Core\Roles\Actions;

use App\Core\Roles\Models\Role;

class CreateRoleAction
{
    public function execute(array $data): Role
    {
        return Role::create([
            'name' => $data['name'],
            'code' => $this->normalizeCode($data['code'] ?? null),
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'is_system' => false,
        ]);
    }

    private function normalizeCode(?string $code): ?string
    {
        if ($code === null || trim($code) === '') {
            return null;
        }

        return strtoupper(trim($code));
    }
}