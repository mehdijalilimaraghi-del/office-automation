<?php

namespace App\Core\Users\Actions;

use App\Models\User;
use App\Core\Audit\Contracts\AuditServiceInterface;
use Illuminate\Validation\ValidationException;

class SyncUserRolesAction
{
    public function __construct(private readonly AuditServiceInterface $audit)
    {
    }

    public function execute(
        User $user,
        array $roleIds
    ): User {
        $oldRoleIds = $user->roles()->pluck('roles.id')->map(fn ($id) => (int) $id)->values()->all();
        $user->roles()->sync($roleIds);

        $this->audit->record('user.roles_synced', $user, ['role_ids' => $oldRoleIds], ['role_ids' => array_values(array_map('intval', $roleIds))]);

        return $user->load('roles');
    }
}