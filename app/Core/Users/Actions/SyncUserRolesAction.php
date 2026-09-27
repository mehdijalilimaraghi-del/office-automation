<?php

namespace App\Core\Users\Actions;

use App\Models\User;
use Illuminate\Validation\ValidationException;

class SyncUserRolesAction
{
    public function execute(
        User $user,
        array $roleIds
    ): User {
        $user->roles()->sync($roleIds);

        return $user->load('roles');
    }
}