<?php

namespace App\Core\Roles\Actions;

use App\Core\Roles\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;

class UpdateRoleAction
{
    public function execute(Role $role, array $data): Role
    {
        if ($role->is_system) {
            throw new HttpException(
                403,
                'نقش سیستمی قابل ویرایش نیست.'
            );
        }

        $role->update([
            'name' => $data['name'],
            'code' => $this->normalizeCode($data['code'] ?? null),
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);

        return $role->refresh();
    }

    private function normalizeCode(?string $code): ?string
    {
        if ($code === null || trim($code) === '') {
            return null;
        }

        return strtoupper(trim($code));
    }
}