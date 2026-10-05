<?php

namespace App\Core\Roles\Actions;

use App\Core\Roles\Models\Role;
use App\Core\Audit\Contracts\AuditServiceInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;

class UpdateRoleAction
{
    public function __construct(private readonly AuditServiceInterface $audit)
    {
    }

    public function execute(Role $role, array $data): Role
    {
        if ($role->is_system) {
            throw new HttpException(
                403,
                'نقش سیستمی قابل ویرایش نیست.'
            );
        }

        $oldValues = $role->only(['name', 'code', 'description', 'is_active']);

        $role->update([
            'name' => $data['name'],
            'code' => $this->normalizeCode($data['code'] ?? null),
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);

        $role->refresh();
        $this->audit->record('role.updated', $role, $oldValues, ['name' => $role->name, 'code' => $role->code, 'is_active' => $role->is_active]);
        return $role;
    }

    private function normalizeCode(?string $code): ?string
    {
        if ($code === null || trim($code) === '') {
            return null;
        }

        return strtoupper(trim($code));
    }
}