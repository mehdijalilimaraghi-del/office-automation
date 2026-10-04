<?php

namespace App\Core\Users\Actions;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class CreateUserAction
{
    public function execute(array $data): User
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'mobile' => $data['mobile'] ?? null,
            'phone' => $data['phone'] ?? null,
            'extension' => $data['extension'] ?? null,
            'password' => Hash::make($data['password']),
            'status' => $data['status'] ?? 'active',
        ]);

        $user->profile()->create([
            'first_name' => $data['first_name'] ?? null,
            'last_name' => $data['last_name'] ?? null,
            'employee_code' => $data['employee_code'] ?? null,
            'national_id' => $data['national_id'] ?? null,
            'birth_date' => $data['birth_date'] ?? null,
        ]);

        return $user;
    }
}
