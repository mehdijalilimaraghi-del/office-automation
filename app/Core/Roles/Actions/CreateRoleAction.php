<?php

namespace App\Core\Roles\Actions;

use App\Core\Roles\Models\Role;
use App\Core\Audit\Contracts\AuditServiceInterface;

class CreateRoleAction
{
    public function __construct(private readonly AuditServiceInterface $audit)
    {
    }

    public function execute(array $data): Role
    {
        $role = Role::create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'is_system' => false,
        ]);

        $this->audit->record('role.created', $role, null, ['name' => $role->name, 'code' => $role->code, 'is_active' => $role->is_active]);

        return $role;
    }
}