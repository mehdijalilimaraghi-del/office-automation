<?php

namespace Tests\Feature\Core\Concerns;

use App\Core\Permissions\Enums\PermissionScope;
use App\Core\Permissions\Models\Permission;
use App\Core\Roles\Models\Role;
use App\Models\User;

trait InteractsWithPermissions
{
    protected function grantPermissions(
        User $user,
        array $permissionCodes
    ): void {
        $role = Role::create([
            'name' => 'Test Administrator',
            'code' => 'TEST_ADMIN',
            'description' => null,
            'is_active' => true,
            'is_system' => false,
        ]);

        $permissions = collect($permissionCodes)
            ->map(
                fn (string $code) => Permission::query()->firstOrCreate(
                    ['code' => $code],
                    [
                        'name' => $code,
                        'description' => null,
                        'is_active' => true,
                        'is_system' => true,
                    ]
                )
            );

        $syncData = $permissions
            ->mapWithKeys(
                fn (Permission $permission) => [
                    $permission->id => [
                        'scope' => PermissionScope::defaultForPermission(
                            $permission->code
                        )->value,
                    ],
                ]
            )
            ->all();

        $role->permissions()->sync($syncData);

        $user->roles()->attach($role->id);
    }
}