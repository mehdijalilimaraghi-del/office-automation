<?php

namespace App\Core\Users\Actions;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UpdateUserAction
{
    public function execute(User $user, array $data): User
    {
        $attributes = [
            'name' => $data['name'],
            'email' => $data['email'],
            'department_id' => $data['department_id'],
            'status' => $data['status'] ?? 'active',
        ];

        if (
            isset($data['password']) &&
            trim($data['password']) !== ''
        ) {
            $attributes['password'] = Hash::make(
                $data['password']
            );
        }

        $user->update($attributes);

        return $user->refresh();
    }
}