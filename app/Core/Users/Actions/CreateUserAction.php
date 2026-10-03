<?php

namespace App\Core\Users\Actions;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class CreateUserAction
{
    public function execute(array $data): User
    {
        return User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'mobile' => $data['mobile'] ?? null,
            'phone' => $data['phone'] ?? null,
            'extension' => $data['extension'] ?? null,
            'password' => Hash::make($data['password']),
            'status' => $data['status'] ?? 'active',
        ]);
    }
}
